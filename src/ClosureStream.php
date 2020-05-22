<?php
/* ===========================================================================
 * Copyright (c) 2018-2020 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure;

/**
 * @internal
 */
class ClosureStream
{
    const STREAM_PROTO = 'closure';

    protected static array $map = [];

    protected static bool $isRegistered = false;

    protected ?string $content;

    protected int $length = 0;

    protected int $pointer = 0;

    public static function url(string $code): string
    {
        $key = \md5($code);
        if (!isset(self::$map[$key])) {
            self::$map[$key] = "<?php\nreturn " . $code . ";";
        }
        return self::STREAM_PROTO . '://' . $key;
    }

    function stream_open($path, $mode, $options, &$opened_path)
    {
        $path = substr($path, strlen(self::STREAM_PROTO . '://'));

        if (!isset(self::$map[$path])) {
            return false;
        }

        $this->content = &self::$map[$path];
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
        if (!static::$isRegistered) {
            static::$isRegistered = stream_wrapper_register(static::STREAM_PROTO, __CLASS__);
        }
    }

    public static function eval(string $______code______, ?array $______vars______ = null): \Closure
    {
        $______code______ = self::url($______code______);

        if ($______vars______) {
            extract($______vars______, EXTR_OVERWRITE | EXTR_REFS);
        } else {
            unset($______vars______);
        }

        return include($______code______);
    }
}
