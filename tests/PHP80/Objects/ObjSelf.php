<?php

namespace Opis\Closure\Test\PHP80\Objects;

class ObjSelf
{
    public \Closure $closure;

    public function __serialize(): array
    {
        return [$this->closure];
    }

    public function __unserialize(array $data): void
    {
        $this->closure = $data[0];
    }
}