<?php

namespace Opis\Closure;

/**
 * @internal
 */
final class CodeStream
{
    public const STREAM_PROTO = 'closure';

    // this must be kept in sync with HANDLERS keys
    private const REGEX = '/^' . self::STREAM_PROTO . ':\/\/([a-z]+)\/(.+)$/';

    private static bool $isRegistered = false;

    /**
     * @var array Handler classes keyed by name
     */
    private static array $handlers = [];

    private ?string $content;

    private int $length = 0;

    private int $pointer = 0;

    /**
     * @var resource Stream resource context
     */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $info = self::info($path);
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

    /**
     * @param array $handlers Class names that extend AbstractInfo
     * @return void
     */
    public static function init(array $handlers = []): void
    {
        if (!self::$isRegistered) {
            self::$isRegistered = stream_wrapper_register(self::STREAM_PROTO, self::class);
        }

        // register handlers
        foreach ($handlers as $class) {
            self::$handlers[$class::name()] = $class;
        }
    }

    public static function include(AbstractInfo $info): mixed
    {
        return include_factory($info->url());
    }

    public static function info(string $url): ?AbstractInfo
    {
        if (!str_starts_with($url, self::STREAM_PROTO . '://')) {
            return null;
        }

        $m = self::classAndKey($url);
        if (!$m) {
            return null;
        }

        return $m[0]::resolve($m[1]);
    }

    private static function classAndKey(string $url): ?array
    {
        $m = null;
        if (!preg_match(self::REGEX, $url, $m)) {
            return null;
        }
        if (!isset(self::$handlers[$m[1]])) {
            return null;
        }
        return [self::$handlers[$m[1]], $m[2]];
    }
}

/**
 * Use this function to get an unbound closure
 * @internal
 */
function include_factory(string $url): mixed {
    return include($url);
}
