<?php

namespace Opis\Closure\Test\PHP80\Objects;

class Entity
{
    public ?Entity $parent = null;
    public array $children = [];

    public function __serialize(): array
    {
        return [$this->parent, $this->children];
    }

    public function __unserialize(array $data): void
    {
        [$this->parent, $this->children] = $data;
    }
}