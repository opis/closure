<?php

namespace Opis\Closure;

use Closure;

#[Attribute\PreventBoxing]
final class ClosureInfo extends AbstractInfo
{
    public const FLAG_IS_SHORT = 1;
    public const FLAG_IS_STATIC = 2;
    public const FLAG_HAS_THIS = 4;
    public const FLAG_HAS_SCOPE = 8;

    public ?array $use;

    public int $flags;

    private ?Closure $factory = null;

    public function __construct(
        string $header,
        string $body,
        ?array $use = null,
        int $flags = 0,
    )
    {
        parent::__construct($header, $body);
        $this->use = $use;
        $this->flags = $flags;
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

    /**
     * @internal
     */
    public function getFactory(?object $thisObj, ?string $scope = null): Closure
    {
        /** @var Closure $factory */
        $factory = ($this->factory ??= CodeStream::include($this));

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
            $reflector = ReflectionClass::get($thisObj);

            if ($reflector->isInternal()) {
                // we cannot bind to internal objects
                return $factory->bindTo($thisObj);
            }

            if ($scope && $scope !== $reflector->name) {
                // we have a different scope than the object
                // this usually happens if the closure is bound
                // in a super class and has access to private members of the super
                return $factory->bindTo($thisObj, $scope);
            }

            // use the same object as scope
            return $factory->bindTo($thisObj, $thisObj);
        }

        if (
            $scope &&
            $scope !== "static" &&
            $this->hasScope() &&
            !ReflectionClass::get($scope)->isInternal()
        ) {
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
        $data = parent::__serialize();
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
        $this->use = $data['use'] ?? null;
        $this->flags = $data['flags'] ?? 0;
        parent::__unserialize($data);
    }

    public static function name(): string
    {
        return "fn";
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
}