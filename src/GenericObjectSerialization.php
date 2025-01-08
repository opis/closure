<?php

namespace Opis\Closure;

/**
 * @internal
 */
class GenericObjectSerialization
{
    // public const SERIALIZE_CALLBACK = [self::class, "serialize"];
    public const UNSERIALIZE_CALLBACK = [self::class, "unserialize"];

    private const PRIVATE_KEY = "\0?\0";

    public static function serialize(object $object, ReflectionClass $reflection): array
    {
        // public and protected properties
        $data = [];

        // private properties contains on key the class name
        /** @var array[] $private */
        $private = [];

        // according to docs get_mangled_object_vars() uses raw values, bypassing hooks
        foreach (get_mangled_object_vars($object) as $name => $value) {
            if ($name[0] !== "\0") {
                // public property
                $data[$name] = $value;
                continue;
            }

            // remove NUL
            $name = substr($name, 1);

            if ($name[0] === "*") {
                // protected property
                // remove * and NUL
                $name = substr($name, 2);
                $data[$name] = $value;
                continue;
            }

            // private property
            // we have to extract the class
            // and replace the anonymous class name
            [$class, $name] = explode("\0", $name, 2);
            if (str_ends_with($class, "@anonymous")) {
                // handle anonymous class
                $class = $reflection->info()->fullClassName();
                $pos = strrpos($name, "\0");
                if ($pos !== false) {
                    $name = substr($name, $pos + 1);
                }
            }

            $class = strtolower($class);
            $private[$class] ??= [];
            $private[$class][$name] = $value;
        }

        // we save the private values to a special key empty key
        if ($private) {
            $data[self::PRIVATE_KEY] = $private;
        }

        return $data;
    }

    public static function unserialize(array &$data, callable $solve, ReflectionClass $reflection): object
    {
        $object = $reflection->newInstanceWithoutConstructor();

        $private = null;
        if (array_key_exists(self::PRIVATE_KEY, $data)) {
            $private = &$data[self::PRIVATE_KEY];
            unset($data[self::PRIVATE_KEY]);
        }

        if ($data) {
            $solve($object, $data);
        }

        do {
            if ((!$data && !$private) || !$reflection->isUserDefined()) {
                break;
            }

            $class = strtolower($reflection->name);

            // handle private properties
            if (isset($private[$class])) {
                // we solve only when needed
                $solve($object, $private[$class]);
                foreach ($private[$class] as $name => $value) {
                    self::setProperty($reflection, $object, $name, $value, true);
                }
                // done with this class
                unset($private[$class]);
            }

            foreach ($data as $name => $value) {
                if (self::setProperty($reflection, $object, $name, $value)) {
                    unset($data[$name]);
                }
            }
        } while ($reflection = $reflection->getParentClass());

        if ($data) {
            // dynamic properties
            foreach ($data as $name => $value) {
                $object->{$name} = $value;
            }
        }

        return $object;
    }

    private static function setProperty(
        \ReflectionClass $reflection,
        object $object,
        string $name,
        mixed $value,
        bool $privateOnly = false
    ): bool {
        if (!$reflection->hasProperty($name)) {
            return false;
        }

        $property = $reflection->getProperty($name);
        if ($property->isStatic() || ($privateOnly && !$property->isPrivate())) {
            return false;
        }

        if (!$property->hasDefaultValue() || $value !== $property->getDefaultValue()) {
            $property->setAccessible(true);
            $property->setValue($object, $value);
        }

        return true;
    }
}