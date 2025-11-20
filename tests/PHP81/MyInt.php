<?php

namespace Opis\Closure\Test\PHP81;

class MyInt
{
    private int $value;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function read(): int {
        return $this->value;
    }
}