<?php

namespace Opis\Closure;

use ReflectionClass;
use Opis\Closure\Attribute\NoBox;

/**
 * @internal
 */
class ClassInfo
{
    public ReflectionClass $reflector;
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
        $reflector = $this->reflector = new ReflectionClass($className);
        $this->box = empty($reflector->getAttributes(NoBox::class));
        $this->hasMagicSerialize = $reflector->hasMethod("__serialize");
        $this->hasMagicUnserialize = $reflector->hasMethod("__unserialize");
    }

    public function className(): string
    {
        return $this->reflector->name;
    }
}