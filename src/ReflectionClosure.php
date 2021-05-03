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

use Closure, ReflectionFunction;

/**
 * @internal
 */
final class ReflectionClosure extends ReflectionFunction
{
    /**
     * @var bool True if info was initialized
     */
    private bool $initialized = false;

    /**
     * @var CodeWrapper|null Source code wrapper
     */
    private ?CodeWrapper $code = null;

    /**
     * @var bool True if closure has short form
     */
    private bool $isShort = false;

    /**
     * @var bool True if closure is declared as static function or static fn
     */
    private bool $isStatic = false;

    /**
     * @var bool True if there is a reference to $this or parent
     */
    private bool $isBindingReq = false;

    /**
     * @var bool True if there is a reference to static, self or parent
     */
    private bool $isScopeReq = false;

    /**
     * @var string[]|null
     */
    private ?array $use = null;

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

    /**
     * Initialize info
     */
    private function init(): self
    {
        if ($this->initialized) {
            return $this;
        }

        $this->initialized = true;

        if ($info = ReflectionFunctionInfo::getInfo($this)) {
            $this->code = $info['code'];
            $this->isShort = $info['short'] ?? false;
            $this->isStatic = $info['static'] ?? false;
            $this->use = $info['use'] ?? null;
            $this->isBindingReq = $info['this'] ?? false;
            $this->isScopeReq = $info['scope'] ?? false;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getClosureScopeClass(): ?string
    {
        $class = parent::getClosureScopeClass();
        // PHP sets the scope to Closure for some reason
        return !$class || $class->name === Closure::class ? null : $class;
    }

    /**
     * Get the callable form
     * @return callable|null
     */
    public function getCallableForm(): ?callable
    {
        if ($this->getShortName() === '{closure}') {
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
            return $scope->getName() . '::' . $name;
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
        return $this->getShortName() !== '{closure}';
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
     * Checks if the closure was declared using the 'static' keyword
     * @return bool
     */
    public function isStatic(): bool
    {
        if ($this->isInternal()) {
            return false;
        }

        return $this->init()->isStatic;
    }

    /**
     * Checks if the closure was created using the short form
     * @return bool
     */
    public function isShortClosure(): bool
    {
        if ($this->isInternal()) {
            return false;
        }

        return $this->init()->isShort;
    }

    /**
     * Checks if the closure is using: $this or parent
     * @return bool
     */
    public function isBindingRequired(): bool
    {
        if ($this->isInternal()) {
            return false;
        }
        return $this->init()->isBindingReq;
    }

    /**
     * Checks if the closure is using: static, self or parent
     * @return bool
     */
    public function isScopeRequired(): bool
    {
        if ($this->isInternal()) {
            return false;
        }
        return $this->init()->isScopeReq;
    }

    /**
     * Get the PHP code that can recreate the closure
     * @return string|null
     */
    public function getCode(): ?string
    {
        if ($this->isInternal()) {
            return null;
        }

        $code = $this->init()->code;

        if ($code === null) {
            return null;
        }

        return (string)$code;
    }

    /**
     * @return array
     */
    public function getUseVariableNames(): array
    {
        if ($this->init()->isShort) {
            return array_keys($this->getStaticVariables());
        }

        return $this->use ?? [];
    }

    /**
     * @return array
     */
    public function getUseVariables(): array
    {
        if ($this->isInternal()) {
            return [];
        }

        $vars = $this->getStaticVariables();

        if ($this->init()->isShort) {
            return $vars;
        }

        if (!$this->use) {
            return [];
        }

        return array_intersect_key($vars, array_flip($this->use));
    }

    /**
     * @internal
     * @return CodeWrapper|null
     */
    public function getCodeWrapper(): ?CodeWrapper
    {
        return $this->init()->code;
    }
}
