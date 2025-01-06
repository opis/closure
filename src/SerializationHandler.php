<?php

namespace Opis\Closure;

use stdClass, Closure, WeakMap, SplObjectStorage;
use function serialize;

class SerializationHandler
{
    private ?array $arrayMap;

    private ?WeakMap $objectMap;

    private ?SplObjectStorage $priority;

    private ?WeakMap $shouldBox;

    private ?array $info;

    private bool $hasClosures;

    public function serialize(mixed $data): string
    {
        $this->arrayMap = [];
        $this->objectMap = new WeakMap();
        $this->priority = new SplObjectStorage();
        $this->shouldBox = new WeakMap();
        $this->info = [];
        $this->hasClosures = false;

        try {
            // get boxed structure
            $data = $this->handle($data);
            if ($this->hasClosures && $this->priority->count()) {
                // we only need priority when we have closures
                $data = new PriorityWrapper(iterator_to_array($this->priority), $data);
            }
            return serialize($data);
        } finally {
            $this->arrayMap = $this->objectMap = $this->priority = $this->shouldBox = $this->info = null;
        }
    }

    public function handle(mixed $data): mixed
    {
        if (is_object($data)) {
            return $this->handleObject($data);
        }
        if (is_array($data)) {
            return $this->handleArray($data);
        }
        return $data;
    }

    private function shouldBox(ReflectionClassInfo $info): bool
    {
        if (isset($this->shouldBox[$info])) {
            // already marked
            return $this->shouldBox[$info];
        }

        if (!$info->useBoxing) {
            // explicit no box
            return $this->shouldBox[$info] = false;
        }

        if ($info->customSerializer) {
            // we have a custom serializer set
            return $this->shouldBox[$info] = true;
        }

        if ($info->isInternal()) {
            // internal classes are supported with custom serializers only
            return $this->shouldBox[$info] = false;
        }

        // yes, we box by default
        return $this->shouldBox[$info] = true;
    }

    private function &getObjectVars(object $object, ReflectionClassInfo $info): ?array
    {
        if ($serializer = $info->customSerializer ?? null) {
            // we have a custom serializer
            $vars = $serializer($object);
        } elseif ($info->hasMagicSerialize) {
            // we have the magic __serialize
            $vars = $object->__serialize();
        } else {
            // we use a generic object serializer
            $vars = GenericObjectSerialization::serialize($object);
        }

        if (!is_array($vars) || empty($vars)) {
            $vars = null;
        }

        if (!$vars) {
            return $vars;
        }

        return $this->handleArray($vars);
    }

    private function handleObject(object $data): object
    {
        if (
            ReflectionClassInfo::objectIsEnum($data) ||
            ($data instanceof Box) ||
            ($data instanceof ClosureInfo)
        ) {
            // we do need original serialization
            return $data;
        }

        if (isset($this->objectMap[$data])) {
            // already boxed
            return $this->objectMap[$data];
        }

        if ($data instanceof stdClass) {
            // handle stdClass
            $obj = $this->handleStdClass($data);
            $this->priority->attach($obj);
            return $obj;
        }

        if ($data instanceof Closure) {
            // we found closures, mark it
            $this->hasClosures = true;
            // handle Closure
            return $this->handleClosure($data);
        }

        $info = ReflectionClassInfo::get(get_class($data));
        if (!$this->shouldBox($info)) {
            // skip boxing
            return $this->objectMap[$data] = $data;
        }

        if ($info->isAnonymousLike()) {
            $anonInfo = AnonymousClassParser::parse($info);
            $box = new Box(Box::TYPE_ANONYMOUS_CLASS, [null, null]);
            $box->data[0] = &$this->getCachedInfo($anonInfo);
            unset($anonInfo);
        } else {
            $box = new Box(Box::TYPE_OBJECT, [$info->name, null]);
        }

        // Set mapping (before vars!)
        $this->objectMap[$data] = $box;

        // Set vars
        $box->data[1] = &$this->getObjectVars($data, $info);

        // Add to priority
        $this->priority->attach($box);

        return $box;
    }

    private function &handleArray(array &$data): array
    {
        $id = ReflectionClassInfo::getRefId($data);
        if (array_key_exists($id, $this->arrayMap)) {
            return $this->arrayMap[$id];
        }

        $box = [];
        $this->arrayMap[$id] = &$box;

        foreach ($data as $key => &$value) {
            if (is_object($value)) {
                $box[$key] = $this->handleObject($value);
            } elseif (is_array($value)) {
                $box[$key] = &$this->handleArray($value);
            } else {
                $box[$key] = &$value;
            }
            unset($value);
        }

        return $box;
    }

    private function handleStdClass(stdClass $data): stdClass
    {
        $box = new stdClass();
        $this->objectMap[$data] = $box;

        foreach ($data as $key => &$value) {
            if (is_object($value)) {
                $box->{$key} = $this->handleObject($value);
            } elseif (is_array($value)) {
                $box->{$key} = &$this->handleArray($value);
            } else {
                $box->{$key} = &$value;
            }
            unset($value);
        }

        return $box;
    }

    private function handleClosure(Closure $closure): Box
    {
        $box = new Box(0);
        $this->objectMap[$closure] = $box;

        $reflector = new ReflectionClosure($closure);

        if (($callable = $reflector->getCallableForm()) !== null) {
            $box->type = Box::TYPE_CALLABLE;
            $box->data = $this->handle($callable);
            return $box;
        }

        $closureInfo = $reflector->info();

        $box->type = Box::TYPE_CLOSURE;
        $box->data = [];
        $box->data["info"] = &$this->getCachedInfo($closureInfo);

        $object = $closureInfo->hasThis() ? $reflector->getClosureThis() : null;
        $scope = $closureInfo->hasScope() ? $reflector->getClosureScopeClass() : null;

        if ($object && !$closureInfo->isStatic()) {
            $box->data["this"] = $this->handleObject($object);
        }

        // Do not add internal or anonymous scope
        if ($scope && !$scope->isInternal() && !$scope->isAnonymous()) {
            $box->data["scope"] = $scope->getName();
        }

        if ($use = $reflector->getUseVariables()) {
            $box->data["vars"] = &$this->handleArray($use);
        }

        return $box;
    }

    private function &getCachedInfo(AbstractInfo $info): array
    {
        // this way we reduce the serialized string size
        // bonus, at deserialization we can load an existing
        // object by looking at "key" prop
        $key = $info::name() . '/' . $info->key();
        $this->info[$key] ??= $info->__serialize();
        return $this->info[$key];
    }
}