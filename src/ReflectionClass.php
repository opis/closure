<?php

namespace Opis\Closure;

final class ReflectionClass extends \ReflectionClass
{
    // yes, we use U+FF20 instead of @
    public const ANONYMOUS_CLASS_PREFIX = 'anonymous＠';

    /**
     * @var bool True if this class has __serialize()
     */
    private bool $_magicSerialize;

    /**
     * @var bool True if this class has __unserialize()
     */
    private bool $_magicUnserialize;

    /**
     * @var bool True if this class is anonymous or built from fake anonymous
     */
    private bool $_isAnonLike;

    private ?AnonymousClassInfo $_info = null;

    /**
     * @var bool True if this class must be boxed
     */
    public bool $useBoxing;

    /**
     * @var callable|null Custom serialization callback
     */
    public $customSerializer = null;

    /**
     * @var callable|null Custom deserialization callback
     */
    public $customDeserializer = null;

    /**
     * You should not use this ctor directly, use ::get()
     * @param string|object $classOrObject
     * @throws \ReflectionException
     */
    public function __construct(string|object $classOrObject)
    {
        parent::__construct($classOrObject);
        $this->_magicSerialize = $this->hasMethod("__serialize");
        $this->_magicUnserialize = $this->hasMethod("__unserialize");
        $this->_isAnonLike = parent::isAnonymous() || self::isAnonymousClassName($this->name);
        // we always box anonymous
        $this->useBoxing = $this->_isAnonLike || empty($this->getAttributes(Attribute\PreventBoxing::class));
    }

    public function hasMagicSerialize(): bool
    {
        return $this->_magicSerialize;
    }

    public function hasMagicUnserialize(): bool
    {
        return $this->_magicUnserialize;
    }

    /**
     * @return bool True if this class is anonymous or contains ANONYMOUS_CLASS_PREFIX
     */
    public function isAnonymousLike(): bool
    {
        return $this->_isAnonLike;
    }

    public function info(): ?AnonymousClassInfo
    {
        if (!$this->_isAnonLike) {
            // we don't provide info for non-anonymous classes
            return null;
        }
        return $this->_info ??= AnonymousClassParser::parse($this);
    }

    /**
     * @var self[]
     */
    private static array $cache = [];

    public static function get(string|object $class): self
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        return self::$cache[strtolower($class)] ??= new self($class);
    }

    public static function clear(): void
    {
        self::$cache = [];
    }

    private static ?bool $enumExists = null;
    public static function objectIsEnum(object $value): bool
    {
        // enums were added in php 8.1
        self::$enumExists ??= interface_exists(\UnitEnum::class, false);
        return self::$enumExists && ($value instanceof \UnitEnum);
    }

    public static function getRefId(mixed &$reference, ?\SplObjectStorage $keepAlive = null): ?string
    {
        $ref = \ReflectionReference::fromArrayElement([&$reference], 0);
        if (!$ref) {
            return null;
        }

        // we save this so the ref ids cannot be reused while serializing/deserializing
        $keepAlive?->attach($ref);

        return $ref->getId();
    }

    public static function isAnonymousClassName(string $class): bool
    {
        $pos = strrpos($class, '\\');
        if ($pos !== false) {
            $class = substr($class, $pos + 1);
        }
        return str_starts_with($class, self::ANONYMOUS_CLASS_PREFIX);
    }

    public static function getRawProperties(object $object, array $properties, ?string $class = null): array
    {
        $vars = get_mangled_object_vars($object);
        $class ??= get_class($object);
        $prefixes = ["\0$class\0", "\0*\0", ""];

        $data = [];
        foreach ($properties as $name) {
            foreach ($prefixes as $prefix) {
                $prop_name = $prefix . $name;
                if (array_key_exists($prop_name, $vars)) {
                    $data[$name] = $vars[$prop_name];
                    break;
                }
            }
        }

        return $data;
    }
}