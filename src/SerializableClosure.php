<?php
/* ===========================================================================
 * Opis Project
 * http://opis.io
 * ===========================================================================
 * Copyright (c) 2013-2017 Opis Project
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure;

use Closure;
use Serializable;
use SplObjectStorage;
use ReflectionObject;
use ReflectionProperty;

/**
 * Provides a wrapper for serialization of closures
 */
class SerializableClosure implements Serializable
{
    /**
     * @var Closure Wrapped closure
     *
     * @see \Opis\Closure\SerializableClosure::getClosure()
     */
    protected $closure;

    /**
     * @var ReflectionClosure A reflection instance for closure
     *
     * @see \Opis\Closure\SerializableClosure::getReflector()
     */
    protected $reflector;

    /**
     * @var mixed Used at deserialization to hold variables
     *
     * @see \Opis\Closure\SerializableClosure::unserialize()
     * @see \Opis\Closure\SerializableClosure::getReflector()
     */
    protected $code;

    /**
     * @var string Closure's ID
     */
    protected $reference;

    /**
     * @var string Closure scope
     */
    protected $scope;

    /**
     * @var ClosureContext Context of closure, used in serialization
     */
    protected static $context;

    /**
     * @var ISecurityProvider|null
     */
    protected static $securityProvider;

    /**
     * Constructor
     *
     * @param   Closure $closure Closure you want to serialize
     */
    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
        if (static::$context !== null) {
            $this->scope = static::$context->scope;
            $this->scope->toserialize++;
        }
    }

    /**
     * Get the Closure object
     *
     * @return  Closure The wrapped closure
     */
    public function getClosure()
    {
        return $this->closure;
    }

    /**
     * Get the reflector for closure
     *
     * @return  ReflectionClosure
     */
    public function getReflector()
    {
        if ($this->reflector === null) {
            $this->reflector = new ReflectionClosure($this->closure, $this->code);
            $this->code = null;
        }

        return $this->reflector;
    }

    /**
     * Implementation of magic method __invoke()
     */
    public function __invoke()
    {
        return call_user_func_array($this->closure, func_get_args());
    }

    /**
     * Implementation of Serializable::serialize()
     *
     * @return  string  The serialized closure
     */
    public function serialize()
    {
        if ($this->scope === null) {
            $this->scope = new ClosureScope();
            $this->scope->toserialize++;
        }

        if (!$this->scope->serializations++) {
            $this->scope->storage = new SplObjectStorage();
        }

        $scope = $object = null;
        $reflector = $this->getReflector();

        if($reflector->isBindingRequired()){
            $object = $reflector->getClosureThis();
            if($scope = $reflector->getClosureScopeClass()){
                $scope = $scope->name;
            }
        } elseif($reflector->isScopeRequired()) {
            if($scope = $reflector->getClosureScopeClass()){
                $scope = $scope->name;
            }
        }

        $this->reference = spl_object_hash($this->closure);

        $this->scope->storage[$this->closure] = $this;

        $use = $reflector->getUseVariables();
        $code = $reflector->getCode();

        $this->mapByReference($use);

        $ret = \serialize(array(
            'use' => $use,
            'function' => $code,
            'scope' => $scope,
            'this' => $object,
            'self' => $this->reference,
        ));

        if(static::$securityProvider !== null){
            $ret =  '@' . json_encode(static::$securityProvider->sign($ret));
        }

        if (!--$this->scope->serializations && !--$this->scope->toserialize) {
            $this->scope->storage = null;
        }

        return $ret;
    }

    /**
     * Implementation of Serializable::unserialize()
     *
     * @param   string $data Serialized data
     * @throws SecurityException
     */
    public function unserialize($data)
    {
        ClosureStream::register();

        if($data[0] === '@'){
            $data = json_decode(substr($data, 1), true);
            if(static::$securityProvider !== null){
                if(!static::$securityProvider->verify($data)){
                    throw new SecurityException("Your serialized closure might have been modified and it's unsafe to be unserialized." .
                        "Make sure you are using the same security provider, with the same settings, " .
                        "both for serialization and unserialization.");
                }
            }
            $data = $data['closure'];
        }

        $this->code = \unserialize($data);

        $this->code['objects'] = array();

        if ($this->code['use']) {
            $this->code['use'] = array_map(array($this, 'mapPointers'), $this->code['use']);
            extract($this->code['use'], EXTR_OVERWRITE | EXTR_REFS);
        }

        $this->closure = include(ClosureStream::STREAM_PROTO . '://' . $this->code['function']);

        if($this->code['this'] === $this){
            $this->code['this'] = null;
        }

        if ($this->code['scope'] !== null || $this->code['this'] !== null) {
            $this->closure = $this->closure->bindTo($this->code['this'], $this->code['scope']);
        }

        if(!empty($this->code['objects'])){
            foreach ($this->code['objects'] as $item){
                $item['property']->setValue($item['instance'], $item['object']->getClosure());
            }
        }

        $this->code = $this->code['function'];
    }

    /**
     * Wraps a closure and sets the serialization context (if any)
     *
     * @param   Closure $closure Closure to be wrapped
     *
     * @return  self    The wrapped closure
     */
    public static function from(Closure $closure)
    {
        if (static::$context === null) {
            $instance = new static($closure);
        } elseif (isset(static::$context->instances[$closure])) {
            $instance = static::$context->instances[$closure];
        } else {
            $instance = new static($closure);
            static::$context->instances[$closure] = $instance;
        }

        return $instance;
    }

    /**
     * Increments the context lock counter or creates a new context if none exist
     */
    public static function enterContext()
    {
        if (static::$context === null) {
            static::$context = new ClosureContext();
        }

        static::$context->locks++;
    }

    /**
     * Decrements the context lock counter and destroy the context when it reaches to 0
     */
    public static function exitContext()
    {
        if (static::$context !== null && !--static::$context->locks) {
            static::$context = null;
        }
    }

    /**
     * @param string $secret
     */
    public static function setSecretKey($secret)
    {
        if(static::$securityProvider === null){
            static::$securityProvider = new SecurityProvider($secret);
        }
    }

    /**
     * @param ISecurityProvider $securityProvider
     */
    public static function addSecurityProvider(ISecurityProvider $securityProvider)
    {
        static::$securityProvider = $securityProvider;
    }

    /**
     * Wrap closures
     *
     * @param $data
     * @param SplObjectStorage|null $storage
     */
    public static function wrapClosures(&$data, SplObjectStorage $storage = null)
    {
        static::enterContext();

        if($storage === null){
            $storage = new SplObjectStorage();
        }

        if($data instanceof Closure){
            $data = static::from($data);
        } elseif (is_array($data)){
            foreach ($data as &$value){
                static::wrapClosures($value, $storage);
            }
        } elseif($data instanceof \stdClass){
            if(isset($storage[$data])){
                $data = $storage[$data];
                return;
            }
            $data = $storage[$data] = clone($data);
            foreach ($data as &$value){
                static::wrapClosures($value, $storage);
            }
        } elseif (is_object($data) && ! $data instanceof static){
            if(isset($storage[$data])){
                $data = $storage[$data];
                return;
            }
            $instance = $data;
            $reflection = new ReflectionObject($data);
            $filter = ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC;
            $storage[$instance] = $data = $reflection->newInstanceWithoutConstructor();
            foreach ($reflection->getProperties($filter) as $property){
                $property->setAccessible(true);
                $value = $property->getValue($instance);
                if(is_array($value) || is_object($value)){
                    static::wrapClosures($value, $storage);
                }
                $property->setValue($data, $value);
            }
        }

        static::exitContext();
    }


    /**
     * Unwrap closures
     * @param $data
     */
    public static function unwrapClosures(&$data)
    {
        if($data instanceof static){
            $data = $data->getClosure();
        } elseif (is_array($data)){
            foreach ($data as &$value){
                static::unwrapClosures($value);
            }
        }elseif ($data instanceof \stdClass){
            foreach ($data as &$property){
                static::unwrapClosures($property);
            }
        } elseif (is_object($data) && !($data instanceof Closure)){
            $reflection = new ReflectionObject($data);
            $filter = ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC;
            foreach ($reflection->getProperties($filter) as $property){
                $property->setAccessible(true);
                $value = $property->getValue($data);
                if(is_array($value) || is_object($value)){
                    static::unwrapClosures($value);
                    $property->setValue($data, $value);
                }
            }
        }
    }

    /**
     * Internal method used to get a reference from closure
     *
     * @return  Closure A pointer to closure
     */
    protected function &getClosurePointer()
    {
        return $this->closure;
    }

    /**
     * Internal method used to map the pointers on unserialization
     *
     * @param   mixed &$value The value to map
     *
     * @return  mixed   Mapped pointers
     */
    protected function &mapPointers(&$value)
    {
        if ($value instanceof static) {
            $pointer = &$value->getClosurePointer();
            return $pointer;
        } elseif ($value instanceof SelfReference && $value->hash === $this->code['self']){
            $pointer = &$this->getClosurePointer();
            return $pointer;
        }elseif (is_array($value)) {
            $pointer = array_map(array($this, __FUNCTION__), $value);
            return $pointer;
        } elseif ($value instanceof \stdClass) {
            $pointer = (array)$value;
            $pointer = array_map(array($this, __FUNCTION__), $pointer);
            $pointer = (object)$pointer;
            return $pointer;
        } elseif (is_object($value) && !($value instanceof Closure)){
            $pointer = $value;
            $reflection = new ReflectionObject($pointer);
            $filter = ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC;
            foreach ($reflection->getProperties($filter) as $property){
                $property->setAccessible(true);
                $item = $property->getValue($pointer);
                if ($item instanceof SerializableClosure || ($item instanceof SelfReference && $item->hash === $this->code['self'])) {
                    $this->code['objects'][] = array(
                        'instance' => $pointer,
                        'property' => $property,
                        'object' => $item instanceof SelfReference ? $this : $item,
                    );
                } elseif (is_array($item) || is_object($item)) {
                    $property->setValue($pointer, $this->mapPointers($item));
                }
            }
            return $pointer;
        }
        return $value;
    }

    /**
     * Internal method used to map closures by reference
     *
     * @param   mixed &$data
     */
    protected function mapByReference(&$data)
    {
        if ($data instanceof Closure) {
            if($data === $this->closure){
                $data = new SelfReference($this->reference);
                return;
            }

            if (isset($this->scope->storage[$data])) {
                $data = $this->scope->storage[$data];
                return;
            }

            $instance = new static($data);

            if (static::$context !== null) {
                static::$context->scope->toserialize--;
            } else {
                $instance->scope = $this->scope;
            }

            $this->scope->storage[$data] = $instance;
            $data = $instance;
        } elseif (is_array($data)) {
            foreach ($data as &$value){
                $this->mapByReference($value);
            }
        } elseif ($data instanceof \stdClass) {
            $value = (array) $data;
            $this->mapByReference($value);
            $data = (object) $value;
        } elseif (is_object($data) && !$data instanceof SerializableClosure){
            $instance = $data;
            $reflection = new ReflectionObject($data);
            $filter = ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC;
            $data = $reflection->newInstanceWithoutConstructor();
            foreach ($reflection->getProperties($filter) as $property){
                $property->setAccessible(true);
                $value = $property->getValue($instance);
                if(is_array($value) || is_object($value)){
                    $this->mapByReference($value);
                }
                $property->setValue($data, $value);
            }
        }
    }
}
