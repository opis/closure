<?php

namespace Opis\Closure;

use Closure, ReflectionFunction, ReflectionClass;

final class ReflectionClosure extends ReflectionFunction
{
    private ?ClosureInfo $info = null;

    private static ?ClosureInfo $internalFunctionInfo = null;

    /**
     * ReflectionClosure constructor.
     * @param Closure $closure
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function __construct(Closure $closure)
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        parent::__construct($closure);
    }

    public function info(): ClosureInfo
    {
        return $this->info ??= ClosureParser::parse($this) ?? (
            // internal info with no active flags
            self::$internalFunctionInfo ??= new ClosureInfo("", "", null, 0)
        );
    }

    /**
     * @inheritDoc
     */
    public function getClosureScopeClass(): ?ReflectionClass
    {
        $class = parent::getClosureScopeClass();
        // PHP sets the scope to Closure for some reason
        return (!$class || $class->name === Closure::class) ? null : $class;
    }

    /**
     * Get the callable form
     * @return callable|null
     */
    public function getCallableForm(): ?callable
    {
        if (!$this->isFromCallable()) {
            // not from callable
            return null;
        }

        $name = $this->getName();

        if ($this->getNamespaceName()) {
            // User function
            return $name;
        }

        if ($closure_this = $this->getClosureThis()) {
            if (strcasecmp($name, '__invoke') === 0) {
                // Invokable object
                return $closure_this;
            }
            // Invokable method
            return [$closure_this, $name];
        }

        if ($scope = $this->getClosureScopeClass()) {
            // Static method
            return [$scope->getName(), $name];
        }

        // Global function
        return $name;
    }

    /**
     * Checks if the closure was created using Closure::fromCallable()
     * @return bool
     */
    public function isFromCallable(): bool
    {
        return !str_starts_with($this->getShortName(), '{closure');
    }

    /**
     * Checks if the closure was created using a class method
     * @return bool
     */
    public function isClassMethod(): bool
    {
        if (!$this->isFromCallable()) {
            return false;
        }

        if ($this->getNamespaceName()) {
            // We have a namespace, so it is a function
            return false;
        }

        return $this->getClosureScopeClass() !== null;
    }

    /**
     * Checks if the closure was created using a function
     * @return bool
     */
    public function isFunction(): bool
    {
        if (!$this->isFromCallable()) {
            return false;
        }

        if ($this->getNamespaceName()) {
            // We have a namespace, so it is a function
            return true;
        }

        return $this->getClosureScopeClass() === null;
    }

    /**
     * Used variables names
     * @return array
     */
    public function getUseVariableNames(): array
    {
        $info = $this->info();

        if ($info->isShort()) {
            return array_keys($this->getStaticVariables());
        }

        return $info->use ?? [];
    }

    /**
     * All used variables keyed by name
     * @return array
     */
    public function getUseVariables(): array
    {
        if ($this->isInternal()) {
            return [];
        }

        $vars = $this->getStaticVariables();

        if (empty($vars)) {
            return $vars;
        }

        $info = $this->info();

        if ($info->isShort()) {
            return $vars;
        }

        if (!$info->use) {
            return [];
        }

        return array_intersect_key($vars, array_flip($info->use));
    }
}
