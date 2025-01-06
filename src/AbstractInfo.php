<?php

namespace Opis\Closure;

abstract class AbstractInfo
{
    protected ?string $key = null;

    protected string $header;

    protected string $body;

    public function __construct(string $header, string $body)
    {
        $this->header = $header;
        $this->body = $body;
    }

    abstract public function getFactoryPHP(bool $phpTag = true): string;
    abstract public function getIncludePHP(bool $phpTag = true): string;

    /**
     * Unique info key
     * @return string
     */
    final public function key(): string
    {
        if (!isset($this->key)) {
            $this->key = self::createKey($this->header, $this->body);
            // save it to cache
            self::$cache[$this->key] = $this;
        }
        return $this->key;
    }

    final public function url(): string
    {
        return CodeStream::STREAM_PROTO . '://' . static::name() . '/' . $this->key();
    }

    public function __serialize(): array
    {
        $data = [
            "key" => $this->key()
        ];
        if ($this->header) {
            $data["header"] = $this->header;
        }
        $data["body"] = $this->body;
        return $data;
    }

    public function __unserialize(array $data): void
    {
        $key = $this->key = $data["key"];
        // in v4.0.0 header was named imports, handle that case too
        $this->header = $data["header"] ?? $data["imports"] ?? "";
        $this->body = $data["body"];

        // populate cache on deserialization
        if ($key && !isset(self::$cache[$key])) {
            // save it to cache
            self::$cache[$key] = $this;
        }
    }

    /**
     * @var static[]
     */
    private static array $cache = [];

    /**
     * @var \ReflectionClass[]
     */
    private static array $reflector = [];

    /**
     * @return string Unique short name
     */
    abstract public static function name(): string;

    /**
     * Loads info from cache or rebuilds from serialized data
     * @param array $data
     * @return static
     */
    final public static function load(array $data): static
    {
        $key = $data["key"] ?? null;
        if ($key && isset(self::$cache[$key])) {
            // found in cache
            return self::$cache[$key];
        }

        /** @var static $obj */
        $obj = (self::$reflector[static::name()] ??= new \ReflectionClass(static::class))->newInstanceWithoutConstructor();

        $obj->__unserialize($data);

        return $obj;
    }

    final public static function clear(): void
    {
        self::$cache = [];
    }

    final public static function resolve(string $key): ?static
    {
        return self::$cache[$key] ?? null;
    }

    final public static function createKey(string $header, string $body): string
    {
        // yes, there was a mistake in params order, keep the body first
        $code = "$body\n$header";
        return $code === "\n" ? "" : md5($code);
    }
}