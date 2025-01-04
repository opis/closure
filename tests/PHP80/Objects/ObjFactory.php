<?php

namespace Opis\Closure\Test\PHP80\Objects;

class ObjFactory
{
    private $value;
    public \Closure $factory;
    public function __construct($value)
    {
        $this->value = $value;
        $this->factory = fn() => $this->value;
    }

    public function getValue()
    {
        return ($this->factory)();
    }
}