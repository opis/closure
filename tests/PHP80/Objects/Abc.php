<?php

namespace Opis\Closure\Test\PHP80\Objects;

use Closure;

class Abc
{
    private Closure $f;

    public function __construct(Closure $f)
    {
        $this->f = $f;
    }

    public function getF(): Closure
    {
        return $this->f;
    }

    public function test($value)
    {
        $f = $this->f;
        return $f($value);
    }

    public function __serialize(): array
    {
        return [$this->f];
    }

    public function __unserialize(array $data): void
    {
        $this->f = $data[0];
    }
}