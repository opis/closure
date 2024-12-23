<?php

namespace Opis\Closure\Test\PHP80\Objects;

class ParentClass
{
    private array $foobar = ['test'];

    public function getFoobar(): array
    {
        return $this->foobar;
    }
}