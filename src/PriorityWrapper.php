<?php

namespace Opis\Closure;

/**
 * In order to correctly unserialize closures we first have to
 * unserialize objects so that bindings of closures to be already resolved.
 * This is used only at the top level.
 * @internal
 */
#[Attribute\PreventBoxing]
final class PriorityWrapper
{
    public function __construct(
        public array $objects,
        public mixed $data,
    )
    {
    }

    public function __serialize(): array
    {
        return [$this->objects, $this->data];
    }

    public function __unserialize(array $data): void
    {
        [$this->objects, $this->data] = $data;
    }
}