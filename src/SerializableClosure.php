<?php
/* ===========================================================================
 * Copyright 2018-2021 Zindex Software
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
use function unserialize;

/**
 * @deprecated Used only for 3.x unserialization
 */
class SerializableClosure implements Serializable
{
    /** Array recursive constant **/
    private const ARRAY_RECURSIVE_KEY = '¯\_(ツ)_/¯';

    private ?SplObjectStorage $scope = null;
    private ?string $hash = null;
    private ?array $objects = null;
    protected ?Closure $closure = null;
    protected ?ReflectionClosure $reflector = null;

    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
    }

    /**
     * @return Closure
     */
    public function getClosure()
    {
        return $this->closure;
    }

    /**
     * @internal
     * @return ReflectionClosure
     */
    public function getReflector()
    {
        if ($this->reflector == null) {
            $this->reflector = new ReflectionClosure($this->closure);
        }
        return $this->reflector;
    }

    public function __invoke()
    {
        return call_user_func_array($this->closure, func_get_args());
    }

    final public function __serialize(): array
    {
        // We serialize using the new serialization mechanism
        return ['closure' => $this->closure];
    }

    final public function __unserialize(array $data): void
    {
        $this->closure = $data['closure'];
    }

    public function serialize()
    {
        // Do not allow serialization from Serializable interface
        // __serialize() should prevent calling this method
        throw new RuntimeException("Invalid serialization method!");
    }

    /**
     * @internal
     * @deprecated
     * @param string $data
     * @throws SecurityException
     */
    public function unserialize($data)
    {
        $data = unserialize(static::decode($data));

        $objects = null;

        if (isset($data['use']) && $data['use']) {
            $this->scope = new SplObjectStorage();
            $this->hash = $data['self'] ?? null;
            $this->objects = [];

            $data['use'] = $this->resolveUseVariables($data['use']);
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
            false
        );

        unset($data);

        if ($objects) {
            foreach ($objects as $item) {
                $item['property']->setValue($item['instance'], $item['object']->getClosure());
            }
        }
    }

    /**
     * Transform the use variables before serialization.
     *
     * @param  array  $data The Closure's use variables
     * @return array
     */
    protected function transformUseVariables($data)
    {
        return $data;
    }

    /**
     * Resolve the use variables after unserialization.
     *
     * @param  array  $data The Closure's transformed use variables
     * @return array
     */
    protected function resolveUseVariables($data)
    {
        return $data;
    }

    /**
     * Internal method used to map closure pointers
     * @param $data
     * @internal
     */
    protected function mapPointers(&$data)
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

    public static function enterContext()
    {
        // Nothing
    }

    public static function exitContext()
    {
        // Nothing
    }

    /**
     * @param Closure $closure
     * @return static
     */
    public static function from(Closure $closure)
    {
        return new static($closure);
    }

    /**
     * Creates a new closure from arbitrary code,
     * emulating create_function, but without using eval
     * @param string $args
     * @param string $code
     * @return Closure
     */
    public static function createClosure($args, $code)
    {
        return SerializableClosureHandler::instance()->createClosure("function({$args}){{$code}}");
    }

    protected static ?ISecurityProvider $securityProvider = null;

    /**
     * @param string $data
     * @return string
     * @throws SecurityException
     */
    protected static function decode(string $data): string
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
     * @param string $secret
     */
    public static function setSecretKey(string $secret): void
    {
        if(static::$securityProvider === null){
            static::$securityProvider = new SecurityProvider($secret);
        }
    }

    /**
     * @param ISecurityProvider $securityProvider
     */
    public static function addSecurityProvider(ISecurityProvider $securityProvider): void
    {
        static::$securityProvider = $securityProvider;
    }

    /**
     * Remove security provider
     */
    public static function removeSecurityProvider(): void
    {
        static::$securityProvider = null;
    }

    /**
     * @return null|ISecurityProvider
     */
    public static function getSecurityProvider(): ?ISecurityProvider
    {
        return static::$securityProvider;
    }
}