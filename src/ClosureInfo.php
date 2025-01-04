<?php

namespace Opis\Closure;

use Closure;

#[Attribute\PreventBoxing]
final class ClosureInfo
{
    public const FLAG_IS_SHORT = 1;
    public const FLAG_IS_STATIC = 2;
    public const FLAG_HAS_THIS = 4;
    public const FLAG_HAS_SCOPE = 8;

    private ?string $key = null;

    private ?Closure $factory = null;

    /**
     * @var ClosureInfo[] Cache by key
     */
    private static array $cache = [];

    public function __construct(
        /**
         * Function imports including namespace
         * @var string
         */
        private string $header,

        /**
         * Function body
         * @var string
         */
        private string $body,

        /**
         * Variable names from use()
         * @var string[]|null
         */
        public ?array  $use = null,

        /**
         * Closure properties
         * @var int
         */
        public int     $flags = 0,
    )
    {
    }

    /**
     * Unique key for this function info
     * @return string
     */
    public function key(): string
    {
        if (!isset($this->key)) {
            $this->key = self::createKey($this->header, $this->body);
            // save it to cache
            self::$cache[$this->key] = $this;
        }
        return $this->key;
    }

    /**
     * Checks if the closure was created using the short form
     * @return bool
     */
    public function isShort(): bool
    {
        return ($this->flags & self::FLAG_IS_SHORT) === self::FLAG_IS_SHORT;
    }

    /**
     * Checks if the closure was declared using the 'static' keyword
     * @return bool
     */
    public function isStatic(): bool
    {
        return ($this->flags & self::FLAG_IS_STATIC) === self::FLAG_IS_STATIC;
    }

    /**
     * Checks if the closure is using: $this or parent
     * @return bool
     */
    public function hasThis(): bool
    {
        return ($this->flags & self::FLAG_HAS_THIS) === self::FLAG_HAS_THIS;
    }

    /**
     * Checks if the closure is using: static, self or parent
     * @return bool
     */
    public function hasScope(): bool
    {
        return ($this->flags & self::FLAG_HAS_SCOPE) === self::FLAG_HAS_SCOPE;
    }

    public function getClosure(?array &$vars = null, ?object $thisObj = null, ?string $scope = null): Closure
    {
        return $this->getFactory($thisObj, $scope)($vars);
    }

    public function getFactory(?object $thisObj, ?string $scope = null): Closure
    {
        $factory = ($this->factory ??= ClosureStream::factory($this));

        if ($thisObj && $this->isStatic()) {
            // closure is static, we cannot bind
            if (!$scope) {
                // we can extract scope
                $scope = get_class($thisObj);
            }
            // remove this
            $thisObj = null;
        }

        if ($thisObj) {
            if (ClassInfo::isInternal($thisObj)) {
                return $factory->bindTo($thisObj);
            }
            return $factory->bindTo($thisObj, $thisObj);
        }

        if ($scope && $scope !== "static" && $this->hasScope() && !ClassInfo::isInternal($scope)) {
            return $factory->bindTo(null, $scope);
        }

        return $factory;
    }

    public function getFactoryPHP(bool $phpTag = true): string
    {
        // we create a random param name to avoid collisions
        $varName = '$___opis_closure_' . rand(1_000_000, 9_999_999) . "___";
        $code = $phpTag ? '<?php' . "\n" : "";
        if ($this->header) {
            $code .= $this->header . "\n";
        }
        $code .= "return function (?array &{$varName} = null): \\Closure {
if ({$varName}) {
    \\extract({$varName}, \\EXTR_SKIP | \\EXTR_REFS);
}
unset({$varName});
// Start of serialized closure
return {$this->body};
// End of serialized closure
};";
        return $code;
    }

    public function getIncludePHP(bool $phpTag = true): string
    {
        $code = $phpTag ? '<?php' . "\n" : "";
        if ($this->header) {
            $code .= $this->header . "\n";
        }
        return $code . "return {$this->body};";
    }

    public function __serialize(): array
    {
        $data = ['key' => $this->key()];
        if ($this->header) {
            $data['imports'] = $this->header;
        }
        $data['body'] = $this->body;
        if ($this->use) {
            $data['use'] = $this->use;
        }
        if ($this->flags) {
            $data['flags'] = $this->flags;
        }
        return $data;
    }

    public function __unserialize(array $data): void
    {
        $this->key = $data['key'] ?? null;
        $this->header = $data['imports'] ?? '';
        $this->body = $data['body'];
        $this->use = $data['use'] ?? null;
        $this->flags = $data['flags'] ?? 0;
        if ($this->key && !isset(self::$cache[$this->key])) {
            // save it to cache
            self::$cache[$this->key] = $this;
        }
    }

    public static function createKey(string $body, ?string $header = null): string
    {
        return md5(($header ?? "") . "\n" . $body);
    }

    public static function flags(
        bool $isShort = false,
        bool $isStatic = false,
        bool $hasThis = false,
        bool $hasScope = false,
    ): int
    {
        $flags = 0;

        if ($isShort) {
            $flags |= self::FLAG_IS_SHORT;
        }

        if ($isStatic) {
            $flags |= self::FLAG_IS_STATIC;
        }

        if ($hasThis) {
            $flags |= self::FLAG_HAS_THIS;
        }

        if ($hasScope) {
            $flags |= self::FLAG_HAS_SCOPE;
        }

        return $flags;
    }

    public static function resolve(string $key): ?ClosureInfo
    {
        return self::$cache[$key] ?? null;
    }

    /**
     * Clears cache
     * @return void
     */
    public static function clear(): void
    {
        self::$cache = [];
    }
}