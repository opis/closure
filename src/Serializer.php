<?php

namespace Opis\Closure;

use UnitEnum, ReflectionClass;
use Opis\Closure\Attribute\NoBox;
use Opis\Closure\Security\{
    DefaultSecurityProvider,
    SecurityProviderInterface,
    SecurityException
};

final class Serializer
{
    private static bool $init = false;

    public static string $uniqKey;

    private static ?SecurityProviderInterface $securityProvider = null;
    private static bool $enumExists;

    public static bool $v3Compatible = false;

    public static function init(
        SecurityProviderInterface|string|null $security = null,
        bool                                  $v3Compatible = false
    ): void
    {
        if (self::$init) {
            return;
        }
        self::$init = true;

        // Handle v3compatible data
        self::$v3Compatible = $v3Compatible;

        // Add missing defines
        $const = [
            // available only from php 8.1
            'T_ENUM',
            'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG',
        ];
        foreach ($const as $key => $value) {
            if (!defined($value)) {
                define($value, -(100 + $key));
            }
        }

        // Init closure parser
        ClosureParser::init();

        // Init closure stream protocol
        ClosureStream::init();

        // Set security provider
        self::setSecurityProvider($security);

        // Set uniq key
        self::$uniqKey = '@(opis/closure):key:' . chr(0) . uniqid() . chr(8);

        // Check for enums
        self::$enumExists = interface_exists(UnitEnum::class, false);

        // add spl serializations
        self::setCustomSerialization(
            \ArrayObject::class,
            [CustomSplSerialization::class, "sArrayObject"],
            [CustomSplSerialization::class, "uArrayObject"],
        );
        self::setCustomSerialization(
            \SplDoublyLinkedList::class,
            [CustomSplSerialization::class, "sDoublyLinkedList"],
            [CustomSplSerialization::class, "uDoublyLinkedList"],
        );
        self::setCustomSerialization(
            \SplStack::class,
            [CustomSplSerialization::class, "sStack"],
            [CustomSplSerialization::class, "uStack"],
        );
        self::setCustomSerialization(
            \SplQueue::class,
            [CustomSplSerialization::class, "sQueue"],
            [CustomSplSerialization::class, "uQueue"],
        );
        self::setCustomSerialization(
            \SplPriorityQueue::class,
            [CustomSplSerialization::class, "sPriorityQueue"],
            [CustomSplSerialization::class, "uPriorityQueue"],
        );
        self::setCustomSerialization(
            \SplObjectStorage::class,
            [CustomSplSerialization::class, "sObjectStorage"],
            [CustomSplSerialization::class, "uObjectStorage"],
        );
        self::setCustomSerialization(
            \SplFixedArray::class,
            [CustomSplSerialization::class, "sFixedArray"],
            [CustomSplSerialization::class, "uFixedArray"],
        );
        self::setCustomSerialization(
            \SplMinHeap::class,
            [CustomSplSerialization::class, "sHeap"],
            [CustomSplSerialization::class, "uMinHeap"],
        );
        self::setCustomSerialization(
            \SplMaxHeap::class,
            [CustomSplSerialization::class, "sHeap"],
            [CustomSplSerialization::class, "uMaxHeap"],
        );
        self::setCustomSerialization(
            \WeakMap::class,
            [CustomSplSerialization::class, "sWeakMap"],
            [CustomSplSerialization::class, "uWeakMap"],
        );
        self::setCustomSerialization(
            \WeakReference::class,
            [CustomSplSerialization::class, "sWeakReference"],
            [CustomSplSerialization::class, "uWeakReference"],
        );
    }

    public static function serialize(mixed $data, ?SecurityProviderInterface $security = null): string
    {
        self::$init || self::init();
        return self::encode((new SerializationHandler())->serialize($data), $security);
    }

    /**
     * @throws SecurityException
     */
    public static function unserialize(string $data, ?SecurityProviderInterface $security = null): mixed
    {
        self::$init || self::init();

        $skipDecode = false;
        // when $data starts with @ - it indicates that it is v4 signed
        if (self::$v3Compatible && ($data[0] ?? null) !== "@") {
            // in v3 only the content of SerializableClosure is signed
            // we must use some simple heuristics to determine if this is v3
            // this will only work if the serialized data contains a closure
            // the security checks will be made inside SerializableClosure::unserialize()
            $skipDecode = str_contains($data, 'C:32:"Opis\Closure\SerializableClosure"');
        }

        // Create a new deserialization handler
        $handler = new DeserializationHandler();

        if (!$skipDecode) {
            // current - v4
            return $handler->unserialize(self::decode($data, $security));
        }

        // v3
        if (!$security || self::$securityProvider === $security) {
            return $handler->unserialize($data);
        }

        // we have to use the current security provider
        $prevSecurity = self::$securityProvider;
        self::$securityProvider = $security;

        try {
            return $handler->unserialize($data);
        } finally {
            self::$securityProvider = $prevSecurity;
        }
    }

    /**
     * Unserialize data from v3.x using a security provider (optional)
     * DO NOT use this to unserialize data from v4
     * This method was created in order to help with migration from v3 to v4
     * @throws SecurityException
     */
    public static function unserialize_v3(string $data, ?SecurityProviderInterface $security = null): mixed
    {
        self::$init || self::init();

        $security ??= self::$securityProvider;

        $enabled = self::$v3Compatible;
        $prevSecurity = self::$securityProvider;

        self::$v3Compatible = true;
        self::$securityProvider = $security;

        try {
            return (new DeserializationHandler())->unserialize($data);
        } finally {
            self::$v3Compatible = $enabled;
            self::$securityProvider = $prevSecurity;
        }
    }

    /**
     * Sign data using a security provider
     */
    public static function encode(string $data, ?SecurityProviderInterface $security = null): string
    {
        $security ??= self::$securityProvider;
        if (!$security) {
            return $data;
        }
        return '@' . $security->sign($data) . "\n" . $data;
    }

    /**
     * Extract signed data using a security provider
     * @throws SecurityException
     */
    public static function decode(string $data, ?SecurityProviderInterface $security = null): string
    {
        $security ??= self::$securityProvider;
        // we must use here the security provider
        if (!$security) {
            if ($data[0] === '@') {
                throw new SecurityException("The serialized closure is signed, use a security provider.");
            }
            return $data;
        }

        if ($data[0] !== '@') {
            throw new SecurityException("The serialized closure is NOT signed, but a security provider was used.");
        }

        [$hash, $data] = explode("\n", $data, 2);

        if (!$security->verify(substr($hash, 1), $data)) {
            throw new SecurityException(
                "Your serialized closure might have been modified and it's unsafe to be unserialized. " .
                "Make sure you use the same security provider, with the same settings, " .
                "both for serialization and unserialization."
            );
        }

        return $data;
    }

    private static array $info = [];

    /**
     * @param string $class
     * @return object
     * @throws \ReflectionException
     */
    public static function classInfo(string $class): object
    {
        if (isset(self::$info[$class])) {
            return self::$info[$class];
        }

        $reflector = new ReflectionClass($class);

        return self::$info[$class] = (object)[
            // "reflector" => $reflector,
            // mark it as internal
            "internal" => $reflector->isInternal(),
            // mark it as serializable
            "serializable" => $reflector->hasMethod("__serialize"),
            // we don't box when we have NoBox attr on class
            "box" => empty($reflector->getAttributes(NoBox::class)),
            // custom serialize/unserialize functions
            // "serialize" => null,
            // "unserialize" => null,
        ];
    }

    /**
     * Prevent serialization boxing for specified classes
     * @param string ...$class
     * @return void
     * @throws \ReflectionException
     */
    public static function preventBoxing(string ...$class): void
    {
        foreach ($class as $cls) {
            self::classInfo($cls)->box = false;
        }
    }

    public static function getSerializer(string $class): ?callable
    {
        return self::classInfo($class)->serialize ?? null;
    }

    public static function getUnserializer(string $class): ?callable
    {
        return self::classInfo($class)->unserialize ?? null;
    }

    /**
     * Use a generic object serializer/deserializer for specified classes
     */
    public static function setObjectSerialization(string ...$class): void
    {
        $serialize = [CustomSplSerialization::class, "sObject"];
        $unserialize = [CustomSplSerialization::class, "uObject"];
        foreach ($class as $cls) {
            $data = self::classInfo($cls);
            if ($data->serializable || !$data->box) {
                // already serializable or boxing is prevented
                continue;
            }
            $data->serialize = $serialize;
            $data->unserialize = $unserialize;
        }
    }

    /**
     * Use custom serialization/deserialization for a class
     */
    public static function setCustomSerialization(string $class, ?callable $serialize, ?callable $unserialize): void
    {
        $data = self::classInfo($class);
        $data->serialize = $serialize;
        $data->unserialize = $unserialize;
    }

    /**
     * Set current security provider
     */
    public static function setSecurityProvider(SecurityProviderInterface|null|string $security): void
    {
        if (is_string($security)) {
            $security = new DefaultSecurityProvider($security);
        }
        self::$securityProvider = $security;
    }

    /**
     * Get current security provider
     */
    public static function getSecurityProvider(): ?SecurityProviderInterface
    {
        return self::$securityProvider;
    }

    /**
     * Helper function to detect if a value is Enum
     * @internal
     */
    public static function isEnum(mixed $value): bool
    {
        return self::$enumExists && ($value instanceof UnitEnum);
    }
}