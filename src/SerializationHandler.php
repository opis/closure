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

    private bool $hasAnonymousObjects;

    public function serialize(mixed $data): string
    {
        $this->arrayMap = [];
        $this->objectMap = new WeakMap();
        $this->priority = new SplObjectStorage();
        $this->shouldBox = new WeakMap();
        $this->hasAnonymousObjects = false;

        try {
            // get boxed structure
            $data = $this->handle($data);
            if ($this->hasAnonymousObjects && $this->priority->count()) {
                // we only need priority when we have closures
                $data = new PriorityWrapper(iterator_to_array($this->priority), $data);
            }
            return serialize($data);
        } finally {
            $this->arrayMap = $this->objectMap = $this->priority = $this->shouldBox = null;
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

    private function shouldBox(ClassInfo $info): bool
    {
        if (isset($this->shouldBox[$info])) {
            // already marked
            return $this->shouldBox[$info];
        }

        if (!$info->box) {
            // explicit no box
            return $this->shouldBox[$info] = false;
        }

        if ($info->serialize) {
            // we have a custom serializer set
            return $this->shouldBox[$info] = true;
        }

        if ($info->reflection->isInternal()) {
            // internal classes are supported with custom serializers only
            return $this->shouldBox[$info] = false;
        }

        // yes, we box by default
        return $this->shouldBox[$info] = true;
    }

    private function handleObject(object $data): object
    {
        if (
            ClassInfo::isEnum($data) ||
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
            $this->hasAnonymousObjects = true;
            // handle Closure
            return $this->handleClosure($data);
        }

        $info = ClassInfo::get(get_class($data));
        if (!$this->shouldBox($info)) {
            // skip boxing
            return $this->objectMap[$data] = $data;
        }

        $box = $this->objectMap[$data] = new Box(Box::TYPE_OBJECT, [$info->className(), null]);

        if ($serializer = $info->serialize ?? null) {
            // we have a custom serializer
            $vars = $serializer($data);
        } elseif ($info->hasMagicSerialize) {
            // we have the magic __serialize
            $vars = $data->__serialize();
        } else {
            // we use a generic object serializer
            $vars = GenericObjectSerialization::serialize($data);
        }

        if (!empty($vars) && is_array($vars)) {
            $box->data[1] = &$this->handleArray($vars);
        }

        $this->priority->attach($box);

        return $box;
    }

    private function &handleArray(array &$data): array
    {
        $id = ClassInfo::refId($data);

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
        $box->data = [
            "info" => $closureInfo,
        ];

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
}