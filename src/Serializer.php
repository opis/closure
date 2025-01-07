<?php

namespace Opis\Closure;

use Opis\Closure\Security\{
    DefaultSecurityProvider,
    SecurityProviderInterface,
    SecurityException
};

/**
 * @internal
 */
final class Serializer
{
    private static bool $init = false;

    private static ?SecurityProviderInterface $securityProvider = null;

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

        // Init parser
        AbstractParser::init();

        // Init code stream protocol
        CodeStream::init([ClosureInfo::class, AnonymousClassInfo::class]);

        // Set security provider
        if ($security) {
            self::setSecurityProvider($security);
        }

        // add spl serializations
        self::register(
            \ArrayObject::class,
            [CustomSplSerialization::class, "sArrayObject"],
            [CustomSplSerialization::class, "uArrayObject"],
        );
        self::register(
            \SplDoublyLinkedList::class,
            [CustomSplSerialization::class, "sDoublyLinkedList"],
            [CustomSplSerialization::class, "uDoublyLinkedList"],
        );
        self::register(
            \SplStack::class,
            [CustomSplSerialization::class, "sStack"],
            [CustomSplSerialization::class, "uStack"],
        );
        self::register(
            \SplQueue::class,
            [CustomSplSerialization::class, "sQueue"],
            [CustomSplSerialization::class, "uQueue"],
        );
        self::register(
            \SplPriorityQueue::class,
            [CustomSplSerialization::class, "sPriorityQueue"],
            [CustomSplSerialization::class, "uPriorityQueue"],
        );
        self::register(
            \SplObjectStorage::class,
            [CustomSplSerialization::class, "sObjectStorage"],
            [CustomSplSerialization::class, "uObjectStorage"],
        );
        self::register(
            \SplFixedArray::class,
            [CustomSplSerialization::class, "sFixedArray"],
            [CustomSplSerialization::class, "uFixedArray"],
        );
        self::register(
            \SplMinHeap::class,
            [CustomSplSerialization::class, "sHeap"],
            [CustomSplSerialization::class, "uMinHeap"],
        );
        self::register(
            \SplMaxHeap::class,
            [CustomSplSerialization::class, "sHeap"],
            [CustomSplSerialization::class, "uMaxHeap"],
        );
        self::register(
            \WeakMap::class,
            [CustomSplSerialization::class, "sWeakMap"],
            [CustomSplSerialization::class, "uWeakMap"],
        );
        self::register(
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

    public static function unserialize(string $data, ?SecurityProviderInterface $security = null, ?array $options = null): mixed
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
        $handler = new DeserializationHandler($options);

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
     * Unserialize data from v3 using a security provider (optional)
     * DO NOT use this to unserialize data from v4
     * This method was created in order to help with migration from v3 to v4
     */
    public static function v3_unserialize(string $data, ?SecurityProviderInterface $security = null, ?array $options = null): mixed
    {
        self::$init || self::init();

        $security ??= self::$securityProvider;

        $enabled = self::$v3Compatible;
        $prevSecurity = self::$securityProvider;

        self::$v3Compatible = true;
        self::$securityProvider = $security;

        try {
            return (new DeserializationHandler($options))->unserialize($data);
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

    /**
     * Prevent serialization boxing for specified classes
     * @param string ...$class
     * @return void
     */
    public static function preventBoxing(string ...$class): void
    {
        foreach ($class as $cls) {
            ReflectionClass::get($cls)->useBoxing = false;
        }
    }

    /**
     * Register custom serialization & deserialization for a class
     * @param string $class
     * @param null|callable(object): array $serialize
     * @param null|callable(array, callable(object, mixed): void, \ReflectionClass): object $unserialize
     * @return void
     */
    public static function register(string $class, ?callable $serialize, ?callable $unserialize): void
    {
        $data = ReflectionClass::get($class);
        $data->customSerializer = $serialize;
        $data->customDeserializer = $unserialize;
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
}