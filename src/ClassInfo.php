<?php

namespace Opis\Closure;

use UnitEnum, ReflectionClass, ReflectionReference;

/**
 * @internal
 */
final class ClassInfo
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

    /**
     * @var ClassInfo[]
     */
    private static array $cache = [];

    private static ?bool $enumExists = null;

    private function __construct(string $className)
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

    public static function get(string $class): self
    {
        return self::$cache[$class] ??= new self($class);
    }

    public static function clear(): void
    {
        self::$cache = [];
    }

    public static function isInternal(object|string $object): bool
    {
        return self::get(is_string($object) ? $object : get_class($object))->reflection->isInternal();
    }

    public static function isEnum(mixed $value): bool
    {
        // enums were added in php 8.1
        self::$enumExists ??= interface_exists(UnitEnum::class, false);
        return self::$enumExists && ($value instanceof UnitEnum);
    }

    public static function refId(mixed &$reference): ?string
    {
        return ReflectionReference::fromArrayElement([&$reference], 0)?->getId();
    }
}
