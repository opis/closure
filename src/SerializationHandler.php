<?php

namespace Opis\Closure;

use stdClass, Closure, WeakMap;
use function serialize;

class SerializationHandler
{
    private ?array $arrayMap;

    private ?WeakMap $objectMap;

    /**
     * @var object[]|null
     */
    private ?array $priority;

    /**
     * @var bool[]
     */
    private array $shouldBox = [];

    private int $uniqueArrayKeyValue;

    private bool $hasAnonymousObjects;

    public function serialize(mixed $data): string
    {
        $this->arrayMap = [];
        $this->objectMap = new WeakMap();
        $this->priority = [];
        $this->uniqueArrayKeyValue = 0;
        $this->hasAnonymousObjects = false;

        try {
            // get boxed structure
            $data = $this->handle($data);
            // remove unique key
            foreach ($this->arrayMap as &$pair) {
                unset($pair[0][Serializer::$uniqKey], $pair);
            }
            if ($this->hasAnonymousObjects) {
                // we only need priority when we have closures
                $priority = array_unique($this->priority, \SORT_REGULAR);
                if ($priority) {
                    $data = new PriorityWrapper($priority, $data);
                }
            }
            return serialize($data);
        } finally {
            $this->arrayMap = $this->objectMap = $this->priority = null;
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

    private function shouldBox(string $class): bool
    {
        if (isset($this->shouldBox[$class])) {
            return $this->shouldBox[$class];
        }

        $info = Serializer::classInfo($class);

        if (!$info->box) {
            // explicit no box
            return $this->shouldBox[$class] = false;
        }

        if (isset($info->serialize)) {
            // we have a custom serializer set
            return $this->shouldBox[$class] = true;
        }

        if ($info->internal) {
            // internal classes are supported with custom serializers only
            return $this->shouldBox[$class] = false;
        }

        // shows if __serialize is present
        return $this->shouldBox[$class] = $info->serializable;
    }

    private function handleObject(object $data): object
    {
        if (
            Serializer::isEnum($data) ||
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
            return $this->priority[] = $this->handleStdClass($data);
        }

        if ($data instanceof Closure) {
            // we found closures, mark it
            $this->hasAnonymousObjects = true;
            // handle Closure
            return $this->handleClosure($data);
        }

        $class = get_class($data);

        if (!$this->shouldBox($class)) {
            // skip boxing
            return $this->objectMap[$data] = $data;
        }

        $box = $this->objectMap[$data] = new Box(Box::TYPE_OBJECT, [$class, null]);

        $serializer = Serializer::getSerializer($class);
        $vars = $serializer ? $serializer($data, $this) : $data->__serialize();
        if (!empty($vars) && is_array($vars)) {
            $box->data[1] = &$this->handleArray($vars);
        }

        return $this->priority[] = $box;
    }

    private function &handleArray(array &$data): array
    {
        if (isset($data[Serializer::$uniqKey])) {
            // we must grab the reference to boxed
            return $this->arrayMap[$data[Serializer::$uniqKey]][1];
        }

        $box = [];
        $this->arrayMap[($data[Serializer::$uniqKey] ??= $this->uniqueArrayKeyValue++)] = [&$data, &$box];

        foreach ($data as $key => &$value) {
            if (is_object($value)) {
                $box[$key] = $this->handleObject($value);
            } elseif (is_array($value)) {
                $box[$key] = &$this->handleArray($value);
            } elseif ($key !== Serializer::$uniqKey) {
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