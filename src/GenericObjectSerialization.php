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

    public static function unserialize(array &$data, callable $solve, ReflectionClass $reflection): object
    {
        $object = $reflection->newInstanceWithoutConstructor();

        $solve($object, $data);

        do {
            if (!$data || !$reflection->isUserDefined()) {
                break;
            }
            foreach ($data as $name => $value) {
                if (!$reflection->hasProperty($name)) {
                    continue;
                }

                $property = $reflection->getProperty($name);
                if ($property->isStatic()) {
                    continue;
                }

                $property->setAccessible(true);
                $property->setValue($object, $value);
                unset($data[$name]);
            }
        } while ($reflection = $reflection->getParentClass());

        if ($data) {
            // dynamic
            foreach ($data as $name => $value) {
                $object->{$name} = $value;
            }
        }

        return $object;
    }
}