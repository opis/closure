<?php

namespace Opis\Closure\Test\PHP81;

use Closure;

class ReadonlyPropertyClass
{
    public readonly Closure $func;

    public function __construct()
    {
        $this->func = fn() => $this;
    }

    public function __serialize(): array
    {
        return [$this->func];
    }

    public function __unserialize(array $data): void
    {
        $this->func = $data[0];
    }
}