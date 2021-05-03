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

/**
 * @internal
 */
final class ClosureStream
{
    const STREAM_PROTO = 'closure';

    /**
     * @var CodeWrapper[]
     */
    private static array $map = [];

    private static bool $isRegistered = false;

    private ?string $content;

    private int $length = 0;

    private int $pointer = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $path = substr($path, strlen(self::STREAM_PROTO . '://'));

        if (!isset(self::$map[$path])) {
            return false;
        }

        $this->content = self::$map[$path]->value();
        $this->length = strlen($this->content);

        return true;
    }

    public function stream_close(): void
    {
        unset($this->content);
        $this->length = 0;
        $this->pointer = 0;
    }

    public function stream_read(int $count): string
    {
        $value = substr($this->content, $this->pointer, $count);
        $this->pointer += $count;
        return $value;
    }

    public function stream_eof(): bool
    {
        return $this->pointer >= $this->length;
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return false;
    }

    public function stream_stat(): array
    {
        $stat = stat(__FILE__);
        $stat[7] = $stat['size'] = $this->length;
        return $stat;
    }

    public function url_stat(string $path, int $flags): array
    {
        $stat = stat(__FILE__);
        $stat[7] = $stat['size'] = $this->length;
        return $stat;
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        $crt = $this->pointer;

        switch ($whence) {
            case SEEK_SET:
                $this->pointer = $offset;
                break;
            case SEEK_CUR:
                $this->pointer += $offset;
                break;
            case SEEK_END:
                $this->pointer = $this->length + $offset;
                break;
        }

        if ($this->pointer < 0 || $this->pointer >= $this->length) {
            $this->pointer = $crt;
            return false;
        }

        return true;
    }

    public function stream_tell(): int
    {
        return $this->pointer;
    }

    public static function register(): void
    {
        if (!self::$isRegistered) {
            self::$isRegistered = stream_wrapper_register(self::STREAM_PROTO, __CLASS__);
        }
    }

    public static function url(CodeWrapper $code): string
    {
        $key = $code->key();
        if (!isset(self::$map[$key])) {
            self::$map[$key] = $code;
        }
        return self::STREAM_PROTO . '://' . $key;
    }

    public static function eval(CodeWrapper $code, ?array $vars = null): Closure
    {
        return $vars
            ? include_closure_internal(self::url($code), $vars)
            : include_closure_internal(self::url($code));
    }
}

/**
 * Use this function to get an unbound closure
 * @internal
 */
function include_closure_internal() {
    if (func_num_args() > 1 && (${'#vars'} = func_get_arg(1))) {
        extract(${'#vars'}, EXTR_SKIP | EXTR_REFS);
        unset(${'#vars'});
    }

    /** @noinspection PhpIncludeInspection */
    return include(func_get_arg(0));
}
