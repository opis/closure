<?php

namespace Opis\Closure;

use stdClass, WeakMap, Closure, SplObjectStorage;
use function unserialize;

/**
 * @internal
 */
class DeserializationHandler
{
    private ?WeakMap $unboxed = null;
    private ?WeakMap $refs = null;
    private ?array $visitedArrays = null;
    private array $options;

    private ?SplObjectStorage $refKeepAlive;

    public function __construct(?array $options = null)
    {
        $this->options = $options ?? [];
    }

    public function unserialize(string $serialized): mixed
    {
        $this->unboxed = new WeakMap();
        $this->refs = new WeakMap();
        $this->visitedArrays = [];
        $this->refKeepAlive = new SplObjectStorage();

        if (Serializer::$v3Compatible) {
            $this->v3_unboxed = [];
            $this->v3_refs = [];
        }

        try {
            $data = unserialize($serialized, $this->options);
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
            $this->unboxed = $this->refs = $this->visitedArrays = $this->refKeepAlive = null;
            $this->v3_unboxed = $this->v3_refs = null;
        }
    }

    private function handle(mixed &$data): void
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
        $id = ReflectionClass::getRefId($array, $this->refKeepAlive);
        if (!isset($this->visitedArrays[$id])) {
            $this->visitedArrays[$id] = true;
            $this->handleIterable($array);
        }
    }

    private function handleObject(object &$object): void
    {
        if (isset($this->unboxed[$object])) {
            // already unboxed, use cache
            $object = $this->unboxed[$object];
            return;
        }

        if (!($object instanceof Box)) {
            // 3.x compatibility
            if (Serializer::$v3Compatible && $this->v3_handleObject($object)) {
                return;
            }

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
            Box::TYPE_OBJECT => $this->unboxObject($object, false),
            Box::TYPE_CLOSURE => $this->unboxClosure($object),
            Box::TYPE_CALLABLE => $this->unboxCallable($object),
            Box::TYPE_ANONYMOUS_CLASS => $this->unboxObject($object, true),
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

    private function unboxObject(Box $box, bool $isAnonymous): object
    {
        // resolve class name
        if ($isAnonymous) {
            $class = AnonymousClassInfo::load($box->data[0])->loadClass();
        } else {
            $class = $box->data[0];
        }

        // get reflection info
        $info = ReflectionClass::get($class);

        // get a reference to data
        $data = &$box->data[1];
        // we must always have an array
        $data ??= [];

        $unserialize = $info->customDeserializer;
        if (!$unserialize && !$info->hasMagicUnserialize()) {
            // if we don't have a custom deserializer, and we don't have __unserialize
            // then use the generic object unserialize
            $unserialize = GenericObjectSerialization::UNSERIALIZE_CALLBACK;
        }
        if ($unserialize) {
            return $unserialize($data, function (?object $object, mixed &$value = null) use ($box): void {
                if ($object) {
                    // eagerly cache
                    $this->unboxed[$box] = $object;
                    $this->unboxed[$object] = $object;
                }
                // handle value
                if ($value) {
                    if (is_array($value)) {
                        $this->handleIterable($value);
                    } elseif (is_object($value)) {
                        $this->handleObject($value);
                    }
                }
            }, $info);
        }

        // create a new object
        $object = $info->newInstanceWithoutConstructor();

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

        if (is_array($callable)) {
            if (isset($callable[2])) {
                // load anonymous class definition if any
                AnonymousClassInfo::load($callable[2])->loadClass();
                unset($callable[2]);
            }
            if (is_object($callable[0])) {
                $this->handleObject($callable[0]);
            }
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

        if ($data["this"]) {
            $this->handleObject($data["this"]);
        }

        if ($data["vars"]) {
            $this->handleArray($data["vars"]);
        }

        if (isset($data["anon"])) {
            // load anonymous class definition if any
            AnonymousClassInfo::load($data["anon"])->loadClass();
        }

        // in 4.1 data[info] was the object, we changed it to be an array
        $info = ($data["info"] instanceof ClosureInfo) ? $data["info"] : ClosureInfo::load($data["info"]);

        // get the closure
        return $info->getClosure($data["vars"], $data["this"], $data["scope"]);
    }

    ///////////////////////////////////////////////////////

    /**
     * @var Closure[]|null
     */
    private ?array $v3_unboxed = null;
    private ?array $v3_refs = null;

    private function v3_handleObject(object &$object): bool
    {
        if ($object instanceof SelfReference) {
            $id = $object->hash;

            if (isset($this->v3_unboxed[$id])) {
                // get the resolved closure
                $object = $this->unboxed[$object] = $this->v3_unboxed[$id];
                return true;
            }

            if (isset($this->v3_refs[$id])) {
                // just save a reference
                $this->v3_refs[$id][] = &$object;
                return true;
            }

            // this will be set, eventually
            $this->v3_unboxed[$id] ??= null;
            $object = &$this->v3_unboxed[$id];

            return true;
        }

        if ($object instanceof SerializableClosure) {
            // closure id
            $id = $object->data["self"];
            if (isset($this->v3_unboxed[$id])) {
                // get the resolved closure
                $object = $this->unboxed[$object] = $this->v3_unboxed[$id];
                return true;
            }

            if (isset($this->v3_refs[$id])) {
                // we are currently unboxing the SerializableClosure
                // just save a reference
                $this->v3_refs[$id][] = &$object;
                return true;
            }

            $this->v3_refs[$id] = [];
            $unboxed = $this->v3_unboxClosure($object->data);
            foreach ($this->v3_refs[$id] as &$ref) {
                $ref = $unboxed;
                unset($ref);
            }
            unset($this->v3_refs[$id]);

            // clear data
            $object->data = null;

            // save the object
            $object = $this->unboxed[$object] = $this->v3_unboxed[$id] = $unboxed;
            return true;
        }

        return false;
    }

    private function v3_unboxClosure(array &$data): Closure
    {
        $data += [
            "use" => null,
            "this" => null,
            "scope" => null,
        ];

        $v3_header = "/* v3 */";
        if (!($info = ClosureInfo::resolve(ClosureInfo::createKey($v3_header, $data["function"])))) {
            $flags = 0;

            // use some heuristics for flags
            $cstr = strtolower(ltrim($data["function"]));
            if (str_starts_with($cstr, "static")) {
                $flags |= ClosureInfo::FLAG_IS_STATIC;
                $cstr = ltrim(substr($cstr, 6));
            }
            if (str_starts_with($cstr, "fn")) {
                $flags |= ClosureInfo::FLAG_IS_SHORT;
            }
            unset($cstr);

            if ($data["this"] && !($flags & ClosureInfo::FLAG_IS_STATIC)) {
                $flags |= ClosureInfo::FLAG_HAS_THIS;
            }
            if ($data["scope"]) {
                $flags |= ClosureInfo::FLAG_HAS_SCOPE;
            }

            // create info
            $info = new ClosureInfo($v3_header, $data["function"], $data["use"] ? array_keys($data["use"]) : null, $flags);
        }

        if ($info->isStatic()) {
            // we cannot have objects on static closures
            $data["this"] = null;
        } elseif ($data["this"]) {
            $this->handleObject($data["this"]);
        }

        if ($data["use"]) {
            $this->handleArray($data["use"]);
        }

        return $info->getClosure($data["use"], $data["this"], $data["scope"]);
    }
}
