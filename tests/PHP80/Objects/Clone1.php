<?php

namespace Opis\Closure\Test\PHP80\Objects;

class Clone1
{
    private int $a = 1;

    private function __clone()
    {
    }

    public function value(): int
    {
        return $this->a;
    }

    public function create(): \Closure {
        return function (): int {
            return $this->a;
        };
    }
}