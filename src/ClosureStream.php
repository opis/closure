<?php

namespace Opis\Closure;

use Closure;

/**
 * @internal
 */
final class ClosureStream
{
    const STREAM_PROTO = 'closure://';

    private static bool $isRegistered = false;

    private ?string $content;

    private int $length = 0;

    private int $pointer = 0;

    /**
     * @var resource Stream resource context
     */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $info = ClosureInfo::resolve(substr($path, strlen(self::STREAM_PROTO)));
        if (!$info) {
            return false;
        }

        $this->content = $info->getFactoryPHP();
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

    public static function init(): void
    {
        if (!self::$isRegistered) {
            // we remove ://
            self::$isRegistered = stream_wrapper_register(substr(self::STREAM_PROTO, 0, -3), self::class);
        }
    }

    public static function factory(ClosureInfo $info): Closure
    {
        return include_factory(self::STREAM_PROTO . $info->key());
    }

    public static function info(string $url): ?ClosureInfo
    {
        if (!str_starts_with($url, self::STREAM_PROTO)) {
            return null;
        }
        return ClosureInfo::resolve(substr($url, strlen(self::STREAM_PROTO)));
    }
}

/**
 * Use this function to get an unbound closure
 * @internal
 */
function include_factory(string $url): Closure {
    return include($url);
}
