<?php
/* ===========================================================================
 * Copyright 2018-2020 Zindex Software
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ============================================================================ */

namespace Opis\Closure;

use Closure;
use stdClass;
use Serializable;
use ReflectionObject;
use RuntimeException;
use SplObjectStorage;
use DirectoryIterator;
use function unserialize;

class SerializableClosure implements Serializable
{
    private static bool $init = false;

    /**
     * Preload files and ffi
     * @param bool $compile_files
     */
    public static function preload(bool $compile_files = true): void
    {
        // Preload FFI
        HeaderFile::preload();

        if (!$compile_files) {
            return;
        }

        // Ignore the following files to avoid 'Can't preload already declared class ...' warnings
        $ignore = [
            'HeaderFile.php', // header file used above
            'SerializableClosure.php', // this file
        ];

        // Compile files
        foreach ((new DirectoryIterator(__DIR__)) as $fileInfo) {
            if ($fileInfo->isDot() || !$fileInfo->isFile() ||
                $fileInfo->getExtension() !== 'php' || in_array($fileInfo->getFilename(), $ignore)) {
                continue;
            }

            $script = $fileInfo->getRealPath();

            if (!$script || opcache_is_script_cached($script)) {
                continue;
            }

            opcache_compile_file($script);
        }
    }

    /**
     * Init serializable closures
     * @param array|null $options
     */
    public static function init(?array $options = null): void
    {
        if (self::$init) {
            return;
        }
        self::$init = true;

        self::defines();

        SerializableClosureHandler::init(HeaderFile::load(), $options);
    }

    protected static function defines(): void
    {
        $const = [
            'T_NAME_FULLY_QUALIFIED',
            'T_NAME_QUALIFIED',
            'T_NAME_RELATIVE',
            'T_ATTRIBUTE',
        ];

        foreach ($const as $key => $value) {
            if (!defined($value)) {
                define($value, -(100 + $key));
            }
        }
    }

    // --- 3.x compatibility ---

    /** Array recursive constant **/
    private const ARRAY_RECURSIVE_KEY = '¯\_(ツ)_/¯';

    protected static $securityProvider = null;

    private ?SplObjectStorage $scope = null;
    private ?string $hash = null;
    private ?array $objects = null;
    protected ?Closure $closure = null;

    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
    }

    public function getClosure(): Closure
    {
        return $this->closure;
    }

    public function __invoke()
    {
        return call_user_func_array($this->closure, func_get_args());
    }

    public static function from(Closure $closure): SerializableClosure
    {
        return new self($closure);
    }

    final public function __serialize(): array
    {
        return ['closure' => $this->closure];
    }

    final public function __unserialize(array $data): void
    {
        $this->closure = $data['closure'];
    }

    final public function serialize()
    {
        throw new RuntimeException("Invalid serialization method!");
    }

    final public static function createClosure(string $args, string $code): Closure
    {
        return SerializableClosureHandler::instance()->createClosure("function({$args}){{$code}}");
    }

    /**
     * @param string $data
     * @return string
     * @throws SecurityException
     */
    protected function decode(string $data): string
    {
        if (static::$securityProvider !== null) {
            if ($data[0] !== '@') {
                throw new SecurityException("The serialized closure is not signed. " .
                    "Make sure you use a security provider for both serialization and unserialization.");
            }

            if ($data[1] !== '{') {
                $separator = strpos($data, '.');
                if ($separator === false) {
                    throw new SecurityException('Invalid signed closure');
                }
                $hash = substr($data, 1, $separator - 1);
                $closure = substr($data, $separator + 1);

                $data = ['hash' => $hash, 'closure' => $closure];

                unset($hash, $closure);
            } else {
                $data = json_decode(substr($data, 1), true);
            }

            if (!is_array($data) || !static::$securityProvider->verify($data)) {
                throw new SecurityException("Your serialized closure might have been modified and it's unsafe to be unserialized. " .
                    "Make sure you use the same security provider, with the same settings, " .
                    "both for serialization and unserialization.");
            }

            return $data['closure'];
        }

        if ($data[0] === '@') {
            if ($data[1] !== '{') {
                $separator = strpos($data, '.');
                if ($separator === false) {
                    throw new SecurityException('Invalid signed closure');
                }
                $hash = substr($data, 1, $separator - 1);
                $closure = substr($data, $separator + 1);

                $data = ['hash' => $hash, 'closure' => $closure];

                unset($hash, $closure);
            } else {
                $data = json_decode(substr($data, 1), true);
            }

            if (!is_array($data) || !isset($data['closure']) || !isset($data['hash'])) {
                throw new SecurityException('Invalid signed closure');
            }

            return $data['closure'];
        }

        return $data;
    }

    /**
     * @internal
     * @deprecated
     * @param string $data
     * @throws SecurityException
     */
    public function unserialize($data)
    {
        $data = unserialize($this->decode($data));

        $objects = null;

        if (isset($data['use']) && $data['use']) {
            $this->scope = new SplObjectStorage();
            $this->hash = $data['self'] ?? null;
            $this->objects = [];

            $this->mapPointers($data['use']);
            $objects = $this->objects;

            $this->scope = null;
            $this->hash = null;
            $this->objects = null;
        }

        $this->closure = SerializableClosureHandler::instance()->createClosure(
            $data['function'],
            $data['this'] ?? null,
            $data['scope'] ?? null,
                  $data['use'] ?? null,
        );

        unset($data);

        if ($objects) {
            foreach ($objects as $item) {
                $item['property']->setValue($item['instance'], $item['object']->getClosure());
            }
        }
    }

    /**
     * Internal method used to map closure pointers
     * @param $data
     * @deprecated
     * @internal
     */
    private function mapPointers(&$data): void
    {
        if ($data instanceof self) {
            $data = &$data->closure;
            return;
        }

        if (is_array($data)) {
            if (isset($data[self::ARRAY_RECURSIVE_KEY])) {
                return;
            }

            $data[self::ARRAY_RECURSIVE_KEY] = true;

            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $this->mapPointers($data[$key]);
                } elseif (is_object($value)) {
                    if ($value instanceof Closure) {
                        continue;
                    }
                    if ($value instanceof self) {
                        $data[$key] = &$data[$key]->closure;
                    } elseif (($value instanceof SelfReference) && $value->hash === $this->hash) {
                        $data[$key] = &$this->closure;
                    } else {
                        $this->mapPointers($data[$key]);
                    }
                }
            }

            unset($data[self::ARRAY_RECURSIVE_KEY]);

            return;
        }

        if (!is_object($data) || ($data instanceof Closure)) {
            return;
        }

        if (isset($this->scope[$data])) {
            return;
        }
        $this->scope[$data] = true;

        if ($data instanceof stdClass) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $this->mapPointers($data->{$key});
                } elseif (is_object($value)) {
                    if ($value instanceof Closure) {
                        continue;
                    }
                    if (($value instanceof SelfReference) && $value->hash === $this->hash) {
                        $data->{$key} = &$this->closure;
                    } else {
                        $this->mapPointers($data->{$key});
                    }
                }
            }
            return;
        }

        $reflection = new ReflectionObject($data);

        do {
            if (!$reflection->isUserDefined()) {
                break;
            }

            foreach ($reflection->getProperties() as $property) {
                if ($property->isStatic() || !$property->getDeclaringClass()->isUserDefined()) {
                    continue;
                }

                $property->setAccessible(true);

                if (!$property->isInitialized($data)) {
                    continue;
                }

                $item = $property->getValue($data);

                if (is_array($item)) {
                    $this->mapPointers($item);
                    $property->setValue($data, $item);
                    continue;
                }

                if (!is_object($item)) {
                    continue;
                }

                if ($item instanceof self) {
                    $this->objects[] = [
                        'instance' => $data,
                        'property' => $property,
                        'object' => $item,
                    ];
                    continue;
                }

                if (($item instanceof SelfReference) && $item->hash === $this->hash) {
                    $this->objects[] = [
                        'instance' => $data,
                        'property' => $property,
                        'object' => $this,
                    ];
                    continue;
                }

                if (!($item instanceof Closure)) {
                    $this->mapPointers($item);
                }
            }
        } while ($reflection = $reflection->getParentClass());
    }
}