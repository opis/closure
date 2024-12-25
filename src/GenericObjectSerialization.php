<?php

namespace Opis\Closure;

use ReflectionObject, ReflectionClass;

class GenericObjectSerialization
{
    public static function serialize(object $object): array
    {
        $data = [];
        $skip = [];
        $reflection = new ReflectionObject($object);

        do {
            if (!$reflection->isUserDefined()) {
                foreach ($reflection->getProperties() as $property) {
                    $skip[$property->getName()] = true;
                }
                continue;
            }

            foreach ($reflection->getProperties() as $property) {
                $name = $property->getName();
                $skip[$name] = true;
                if ($property->isStatic() || !$property->getDeclaringClass()->isUserDefined()) {
                    continue;
                }
                $property->setAccessible(true);
                if ($property->isInitialized($object)) {
                    $data[$name] = $property->getValue($object);
                }
            }
        } while ($reflection = $reflection->getParentClass());

        // dynamic
        foreach (get_object_vars($object) as $name => $value) {
            if (!isset($skip[$name])) {
                $data[$name] = $value;
            }
        }

        return $data;
    }

    public static function unserialize(array &$data, callable $solve, string $class): object
    {
        $reflection = new ReflectionClass($class);

        $object = $reflection->newInstanceWithoutConstructor();

        $solve($object, $data);

        foreach ($data as $name => $value) {
            if (!$reflection->hasProperty($name)) {
                // dynamic
                $object->{$name} = $value;
                continue;
            }

            $property = $reflection->getProperty($name);
            if ($property->isStatic()) {
                continue;
            }
            $property->setAccessible(true);
            $property->setValue($object, $value);
        }

        return $object;
    }
}