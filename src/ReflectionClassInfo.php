<?php

namespace Opis\Closure;

/**
 * @internal
 */
final class ReflectionClassInfo extends \ReflectionClass
{
    // yes, we use U+FF20 instead of @
    public const ANONYMOUS_CLASS_PREFIX = 'anonymous＠';

    /**
     * @var bool True if this class must be boxed
     */
    public bool $useBoxing;

    /**
     * @var bool True if this class has __serialize()
     */
    public bool $hasMagicSerialize;

    /**
     * @var bool True if this class has __unserialize()
     */
    public bool $hasMagicUnserialize;

    /**
     * @var bool True if this class is anonymous or built from fake anonymous
     */
    private bool $isAnon;
    private ?AnonymousClassInfo $info = null;

    /**
     * @var callable|null Custom serialization callback
     */
    public $customSerializer = null;

    /**
     * @var callable|null Custom deserialization callback
     */
    public $customDeserializer = null;

    private function __construct(string $class)
    {
        parent::__construct($class);
        $this->hasMagicSerialize = $this->hasMethod("__serialize");
        $this->hasMagicUnserialize = $this->hasMethod("__unserialize");
        $this->isAnon = parent::isAnonymous();
        if (!$this->isAnon) {
            $pos = strrpos($class, '\\');
            if ($pos !== false) {
                $class = substr($class, $pos + 1);
            }
            $this->isAnon = str_starts_with($class, self::ANONYMOUS_CLASS_PREFIX);
        }
        // we always box anonymous
        $this->useBoxing = $this->isAnon || empty($this->getAttributes(Attribute\PreventBoxing::class));
    }

    /**
     * @return bool True if this class is anonymous or contains ANONYMOUS_CLASS_PREFIX
     */
    public function isAnonymousLike(): bool
    {
        return $this->isAnon;
    }

    public function getAnonymousClassInfo(): ?AnonymousClassInfo
    {
        if (!$this->isAnon) {
            return null;
        }
        return $this->info ??= AnonymousClassParser::parse($this);
    }

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

    public static function getRefId(mixed &$reference): ?string
    {
        return \ReflectionReference::fromArrayElement([&$reference], 0)?->getId();
    }
}