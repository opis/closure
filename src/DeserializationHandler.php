<?php

namespace Opis\Closure;

use stdClass, WeakMap, Closure, ReflectionClass;
use function unserialize;

class DeserializationHandler
{
    private ?WeakMap $unboxed = null;
    private ?WeakMap $refs = null;
    private ?array $visitedArrays = null;

    public function unserialize(string $serialized): mixed
    {
        $this->unboxed = new WeakMap();
        $this->refs = new WeakMap();
        $this->visitedArrays = [];

        try {
            $data = unserialize($serialized);
            unset($serialized);

            // handle unboxing
            if ($data instanceof PriorityWrapper) {
                $this->handleIterable($data->objects);
                $this->handle($data->data);
                $data = &$data->data;
            } else {
                $this->handle($data);
            }

            return $data;
        } finally {
            $this->unboxed = $this->refs = $this->visitedArrays = null;
        }
    }

    public function handle(mixed &$data): void
    {
        if (is_object($data)) {
            $this->handleObject($data);
        } elseif (is_array($data)) {
            $this->handleArray($data);
        }
    }

    private function handleIterable(array|object &$iterable): void
    {
        foreach ($iterable as &$value) {
            if (is_array($value)) {
                $this->handleArray($value);
            } else if (is_object($value)) {
                $this->handleObject($value);
            }
            unset($value);
        }
    }

    private function handleArray(array &$array): void
    {
        $visited = &$this->visitedArrays;

        if ($visited) {
            $found = false;
            $array[Serializer::$uniqKey] = true;

            for ($i = count($visited) - 1; $i >= 0; $i--) {
                if (isset($visited[$i][Serializer::$uniqKey])) {
                    $found = true;
                    break;
                }
            }

            unset($array[Serializer::$uniqKey]);

            if ($found) {
                return;
            }
        }

        $visited[] = &$array;
        $this->handleIterable($array);
    }

    private function handleObject(object &$object): void
    {
        if (isset($this->unboxed[$object])) {
            // already unboxed, use cache
            $object = $this->unboxed[$object];
            return;
        }

        if (!($object instanceof Box)) {
            // mark as unboxed (to not process again)
            $this->unboxed[$object] = $object;

            // handle stdClass
            if ($object instanceof stdClass) {
                $this->handleIterable($object);
            }
            return;
        }

        // from here, $object is a Box

        if (isset($this->refs[$object])) {
            // we are currently unboxing, just save a reference
            $this->refs[$object][] = &$object;
            return;
        }

        // setup references array
        $this->refs[$object] = [];

        // start unboxing
        $unboxed = match ($object->type) {
            Box::TYPE_OBJECT => $this->unboxObject($object),
            Box::TYPE_CLOSURE => $this->unboxClosure($object),
            Box::TYPE_CALLABLE => $this->unboxCallable($object),
        };

        // process references
        foreach ($this->refs[$object] as &$ref) {
            $ref = $unboxed;
            unset($ref);
        }

        // remove references array
        unset($this->refs[$object]);

        // mark as unboxed
        $this->unboxed[$object] = $unboxed;
        if ($unboxed) {
            // save it to not process twice
            $this->unboxed[$unboxed] = $unboxed;
        }

        // finally, set result
        $object = $unboxed;
    }

    private function unboxObject(Box $box): object
    {
        /**
         * @var string $class
         * @var array|null $data
         */
        [$class, &$data] = $box->data;

        // we must always have an array
        $data ??= [];

        // check if we have a custom unserialize
        $unserialize = Serializer::getUnserializer($class);
        if ($unserialize) {
            return $unserialize($data, function (?object $object, mixed &$value = null) use ($box, &$data): void {
                if ($object) {
                    // eagerly cache
                    $this->unboxed[$box] = $object;
                    $this->unboxed[$object] = $object;
                }
                if ($value) {
                    // handle
                    $this->handle($value);
                }
            }, $class, $this);
        }

        // create a new object
        $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        // we eagerly save cache
        $this->unboxed[$box] = $object;
        $this->unboxed[$object] = $object;

        // handle data
        if ($data) {
            $this->handleIterable($data);
        }

        // we call the unserialize method
        $object->__unserialize($data);

        // the object should be ready
        return $object;
    }

    private function unboxCallable(Box $box): Closure
    {
        $callable = &$box->data;

        if (is_array($callable) && is_object($callable[0])) {
            $this->handleObject($callable[0]);
        }

        return Closure::fromCallable($callable);
    }

    private function unboxClosure(Box $box): Closure
    {
        $data = &$box->data;

        $data += [
            "vars" => null,
            "this" => null,
            "scope" => null,
        ];

        /** @var $info ClosureInfo */
        $info = $data["info"];

        if ($data["this"]) {
            $this->handleObject($data["this"]);
        }

        if ($data["vars"]) {
            $this->handleArray($data["vars"]);
        }

        return $info->getFactory($data["this"], $data["scope"])($data["vars"]);
    }
}
