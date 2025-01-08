<?php

namespace Opis\Closure;

/**
 * @param Security\SecurityProviderInterface|string|null $security The security provider used to sign data
 * @param bool $v3Compatible True if you need v3 compatibility at deserialization
 * @return void
 */
function init(
    Security\SecurityProviderInterface|string|null $security = null,
    bool                                           $v3Compatible = false
): void
{
    Serializer::init($security, $v3Compatible);
}

/**
 * Changes the security provider
 * @param Security\SecurityProviderInterface|string|null $security
 * @return void
 */
function set_security_provider(Security\SecurityProviderInterface|string|null $security): void
{
    Serializer::setSecurityProvider($security);
}

/**
 * Get current security provider
 * @return Security\SecurityProviderInterface|null
 */
function get_security_provider(): ?Security\SecurityProviderInterface
{
    return Serializer::getSecurityProvider();
}

/**
 * Register a custom serializer for class
 * @param string $class Class name
 * @param callable(object): array $serialize Serialization function
 * @param callable(array, callable(object, mixed): void, \ReflectionClass): object $unserialize Unserialization function
 * @return void
 */
function register(
    string   $class,
    callable $serialize,
    callable $unserialize
): void
{
    Serializer::register($class, $serialize, $unserialize);
}

/**
 * Unregister a previously registered serializer
 * @param string $class Class name
 * @return void
 */
function unregister(string $class): void
{
    Serializer::register($class, null, null);
}

/**
 * Prevent serialization boxing for the specified classes.
 * If you own the class, you should use Opis\Closure\Attribute\PreventBoxing attribute, instead of this function.
 * @param string ...$classes
 * @return void
 */
function prevent_boxing(string ...$classes): void
{
    Serializer::preventBoxing(...$classes);
}

/**
 * Serialize arbitrary data
 * @param mixed $data The data to be serialized
 * @param Security\SecurityProviderInterface|null $security Security provider used to sign the serialized data
 * @return string Serialized data
 */
function serialize(
    mixed                               $data,
    ?Security\SecurityProviderInterface $security = null
): string
{
    return Serializer::serialize($data, $security);
}

/**
 * Unserialize data
 * @param string $data Data to be deserialized
 * @param Security\SecurityProviderInterface|array|null $security The security provider used to check the signature
 * @param array|null $options Options for \unserialize()
 * @return mixed The deserialized data
 * @see https://www.php.net/manual/en/function.unserialize.php
 */
function unserialize(
    string                                        $data,
    Security\SecurityProviderInterface|array|null $security = null,
    ?array                                        $options = null
): mixed
{
    if (is_array($security)) {
        // this is only for compatibility with v3
        return Serializer::unserialize($data, null, $security);
    }
    return Serializer::unserialize($data, $security, $options);
}

/**
 * Unserialize data from v3
 * @param string $data The data to deserialize
 * @param Security\SecurityProviderInterface|null $security Security provider used to check the signature
 * @param array|null $options Options for \unserialize()
 * @return mixed The deserialized data
 */
function v3_unserialize(
    string $data,
    ?Security\SecurityProviderInterface $security = null,
    ?array $options = null
): mixed
{
    return Serializer::v3_unserialize($data, $security, $options);
}

/**
 * Helper method used to replace the deprecated create_function() from PHP
 * @param string $args Comma separated list of function arguments
 * @param string $body Function code
 * @return \Closure
 * @see https://www.php.net/manual/en/function.create-function.php
 */
function create_closure(string $args, string $body): \Closure
{
    Serializer::init();

    $header = "/* created with " . __FUNCTION__ . "() */";
    $body = "static function (" . $args . ") {\n" . $body . "\n}";

    if (!($info = ClosureInfo::resolve(ClosureInfo::createKey($header, $body)))) {
        $info = new ClosureInfo($header, $body, null, ClosureInfo::FLAG_IS_STATIC);
    }

    return $info->getClosure();
}

/**
 * Get the raw values of properties - it won't invoke property hooks
 * @param object $object The object from where to extract raw properties
 * @param string[] $properties Array of property names
 * @param string|null $class If you need a private property from a parent use the class
 * @return array Raw property values keyed by property name
 */
function get_raw_properties(object $object, array $properties, ?string $class = null): array
{
    return ReflectionClass::getRawProperties($object, $properties, $class);
}

/**
 * If you have a long-running process that deserializes closures or anonymous classes, you may want to clear cache
 * to prevent high memory usage.
 * @return void
 */
function clear_cache(): void
{
    AbstractInfo::clear();
    AbstractParser::clear();
    ReflectionClass::clear();
}