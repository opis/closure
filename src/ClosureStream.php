<?php /** @noinspection PhpUnused */

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

    private static ?string $evalFile = null;

    private static ?array $evalVars = null;

    private static ?Closure $evalRet = null;

    private ?string $content;

    private int $length = 0;

    private int $pointer = 0;

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $path = substr($path, strlen(self::STREAM_PROTO . '://'));

        if (!isset(self::$map[$path])) {
            return false;
        }

        $this->content = self::$map[$path]->value();
        $this->length = strlen($this->content);

        return true;
    }

    public function stream_close()
    {
        unset($this->content);
        $this->length = 0;
        $this->pointer = 0;
    }

    public function stream_read($count)
    {
        $value = substr($this->content, $this->pointer, $count);
        $this->pointer += $count;
        return $value;
    }

    public function stream_eof()
    {
        return $this->pointer >= $this->length;
    }

    public function stream_set_option($option, $arg1, $arg2)
    {
        return false;
    }

    public function stream_stat()
    {
        $stat = stat(__FILE__);
        $stat[7] = $stat['size'] = $this->length;
        return $stat;
    }

    public function url_stat($path, $flags)
    {
        $stat = stat(__FILE__);
        $stat[7] = $stat['size'] = $this->length;
        return $stat;
    }

    public function stream_seek($offset, $whence = SEEK_SET)
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

    public function stream_tell()
    {
        return $this->pointer;
    }

    public static function register()
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

    public static function eval(CodeWrapper $code, array $vars): Closure
    {
        $closure = static function () {
            extract(func_get_arg(1), EXTR_OVERWRITE | EXTR_REFS);

            return require func_get_arg(0);
        };

        return $closure(self::url($code), $vars);
    }
}
