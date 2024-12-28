<?php

namespace Opis\Closure;

use ReflectionClass;

/**
 * @internal
 */
class ClassInfo
{
    public ReflectionClass $reflection;
    public bool $box;
    public bool $hasMagicSerialize;
    public bool $hasMagicUnserialize;

    /**
     * @var callable|null
     */
    public $serialize = null;

    /**
     * @var callable|null
     */
    public $unserialize = null;

    public function __construct(string $className)
    {
        $reflection = $this->reflection = new ReflectionClass($className);
        $this->box = empty($reflection->getAttributes(Attribute\PreventBoxing::class));
        $this->hasMagicSerialize = $reflection->hasMethod("__serialize");
        $this->hasMagicUnserialize = $reflection->hasMethod("__unserialize");
    }

    public function className(): string
    {
        return $this->reflection->name;
    }
}