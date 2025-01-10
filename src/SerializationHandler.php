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

    private ?SplObjectStorage $refKeepAlive;

    public function serialize(mixed $data): string
    {
        $this->arrayMap = [];
        $this->objectMap = new WeakMap();
        $this->priority = new SplObjectStorage();
        $this->shouldBox = new WeakMap();
        $this->info = [];
        $this->hasClosures = false;
        $this->refKeepAlive = new SplObjectStorage();

        try {
            // get boxed structure
            $data = $this->handle($data);
            if ($this->hasClosures && $this->priority->count()) {
                // we only need priority when we have closures
                $data = new PriorityWrapper(iterator_to_array($this->priority), $data);
            }
            return serialize($data);
        } finally {
            $this->arrayMap =
            $this->objectMap =
            $this->priority =
            $this->refKeepAlive =
            $this->shouldBox =
            $this->info = null;
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

    private function shouldBox(ReflectionClass $info): bool
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

    private function getObjectVars(object $object, ReflectionClass $info): ?array
    {
        if ($serializer = $info->customSerializer ?? null) {
            // we have a custom serializer
            $vars = $serializer($object, $info);
        } elseif ($info->hasMagicSerialize()) {
            // we have the magic __serialize
            $vars = $object->__serialize();
        } else {
            // we use a generic object serializer
            $vars = GenericObjectSerialization::serialize($object, $info);
        }

        if (!is_array($vars) || !$vars) {
            return null;
        }

        return $this->handleArray($vars, true);
    }

    private function handleObject(object $data): object
    {
        if (
            ReflectionClass::objectIsEnum($data) ||
            ($data instanceof Box) ||
            ($data instanceof AbstractInfo)
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

        $info = ReflectionClass::get(get_class($data));
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
        $box->data[1] = $this->getObjectVars($data, $info);

        // Add to priority
        $this->priority->attach($box);

        return $box;
    }

    private SplObjectStorage $keep;

    private function &handleArray(array &$data, bool $skipRefId = false): array
    {
        if ($skipRefId) {
            $box = [];
        } else {
            $id = ReflectionClass::getRefId($data, $this->refKeepAlive);
            if (array_key_exists($id, $this->arrayMap)) {
                return $this->arrayMap[$id];
            }
            $box = [];
            $this->arrayMap[$id] = &$box;
        }

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
            if (is_object($callable)) {
                $callable = $this->handleObject($callable);
            } else if (is_array($callable)) {
                if (is_object($callable[0])) {
                    $callable[0] = $this->handleObject($callable[0]);
                } else if ($info = ReflectionClass::get($callable[0])->info()) {
                    // we have an anonymous
                    $callable[0] = $info->fullClassName();
                    $callable[2] = &$this->getCachedInfo($info);
                }
            }

            $box->data = $callable;

            return $box;
        }

        $closureInfo = $reflector->info();

        $box->type = Box::TYPE_CLOSURE;
        $box->data = [];
        $box->data["info"] = &$this->getCachedInfo($closureInfo);

        $object = $closureInfo->hasThis() && !$closureInfo->isStatic() ? $reflector->getClosureThis() : null;
        $scope = $closureInfo->hasScope() ? $reflector->getClosureScopeClass() : null;

        if ($object) {
            $box->data["this"] = $this->handleObject($object);
            $scope ??= $reflector->getClosureScopeClass();
        }

        if ($scope && !$scope->isInternal()) {
            $scopeClass = $scope->name;
            if ($scope->isAnonymous() || ReflectionClass::isAnonymousClassName($scopeClass)) {
                if (!$object && $closureInfo->hasScope()) {
                    // this is a tricky case
                    // we don't have $this, but we must make sure the anonymous class is available for static::/self::
                    // this works on a local machine because the class name is something like:
                    // class@anonymous/path/to/file.php:31$0
                    // but on another machine we have to make it available
                    $anonInfo = ReflectionClass::get($scopeClass)->info();
                    $scopeClass = $anonInfo->fullClassName();
                    $box->data["anon"] = &$this->getCachedInfo($anonInfo);
                    unset($anonInfo);
                } else {
                    // we don't need scope when we have $this in anonymous
                    $scopeClass = null;
                }
            }
            if ($scopeClass) {
                $box->data["scope"] = $scopeClass;
            }
            unset($scopeClass);
        }

        if ($use = $reflector->getUseVariables()) {
            $box->data["vars"] = &$this->handleArray($use, true);
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