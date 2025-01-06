<?php

namespace Opis\Closure;

/**
 * @internal
 */
#[Attribute\PreventBoxing]
final class Box
{
    public const TYPE_CLOSURE = 1;
    public const TYPE_CALLABLE = 2;
    public const TYPE_OBJECT = 3;
    public const TYPE_ANONYMOUS_CLASS = 4;

    public function __construct(
        public int $type,
        public mixed $data = null,
    )
    {
    }

    public function __serialize(): array
    {
        return [$this->type, $this->data];
    }

    public function __unserialize(array $data): void
    {
        [$this->type, $this->data] = $data;
    }
}