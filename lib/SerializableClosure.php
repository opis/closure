<?php
/* ===========================================================================
 * Opis Project
 * http://opis.io
 * ===========================================================================
 * Copyright (c) 2014-2016 Opis Project
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure;

use Closure;
use Serializable;
use SplObjectStorage;


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
     * @var mixed Used on unserializations to hold variables
     *
     * @see \Opis\Closure\SerializableClosure::unserialize()
     * @see \Opis\Closure\SerializableClosure::getReflector()
     */
    protected $code;

    /**
     * @var SelfReference Used to fix serialization in PHP 5.3
     */
    protected $reference;

    /**
     * @var boolean Indicates if closure must be serialized with bounded object
     */
    protected $serializeThis = false;

    /**
     * @var string Closure scope
     */
    protected $scope;

    /**
     * @var ClosureContext Context of closure, used in serialization
     */
    protected static $context;

    /**
     * @var boolean Indicates is closures can be bound to objects
     *
     * @see \Opis\Closure\SerializableClosure::supportBinding()
     */
    protected static $bindingSupported;

    /**
     * @var integer Number of unserializations in progress
     *
     * @see \Opis\Closure\SerializableClosure::unserializePHP53()
     */
    protected static $unserializations = 0;

    /**
     * @var array Unserialized closures
     *
     * @see \Opis\Closure\SerializableClosure::unserializePHP53()
     */
    protected static $deserialized;

    /**
     * @var ISecurityProvider|null
     */
    protected static $securityProvider;

    /**
     * Constructor
     *
     * @param   Closure $closure Closure you want to serialize
     * @param   boolean $serializeThis - Deprecated
     */
    public function __construct(Closure $closure, $serializeThis = false)
    {
        $this->closure = $closure;
        $this->serializeThis = $serializeThis;
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
            $this->useVars = null;
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

        if (!static::supportBinding()) {
            $this->reference = new SelfReference($this->closure);
        } else {
            if($reflector->isBindingRequired()){
                $object = $reflector->getClosureThis();
                if($scope = $reflector->getClosureScopeClass()){
                    $scope = $scope->name;
                }
            } elseif($reflector->isScopeRequired()) {
                if($scope = $reflector->getClosureScopeClass()){
                    $scope = $scope->name;
                }
                if($this->serializeThis){
                    $object = $reflector->getClosureThis();
                }
            }
        }

        $this->scope->storage[$this->closure] = $this;

        $use = null;

        $code = $reflector->getCode();

        if ($variables = $reflector->getUseVariables()) {
            $use = &$this->mapByReference($variables);
        }

        $ret = serialize(array(
            'use' => $use,
            'function' => $code,
            'scope' => $scope,
            'this' => $object,
            'self' => $this->reference,
        ));

        if(static::$securityProvider !== null){
            $ret = serialize(static::$securityProvider->sign($ret));
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

        if (!static::supportBinding()) {
            $this->unserializePHP53($data);
            return;
        }

        $this->code = unserialize($data);

        if(isset($this->code['hash'])){
            if(static::$securityProvider !== null){
                if(!static::$securityProvider->verify($this->code)){
                    throw new SecurityException("Your serialized closure might have been modified and it's unsafe to be unserialized." .
                        "Make sure you are using the same security provider, with the same settings, " .
                        "both for serialization and unserialization.");
                }
                $this->code = unserialize($this->code['closure']);
            } else {
                $this->code = unserialize($this->code['closure']);
            }
        }

        if ($this->code['use']) {
            $this->code['use'] = array_map(array($this, 'mapPointers'), $this->code['use']);
            extract($this->code['use'], EXTR_OVERWRITE | EXTR_REFS);
        }

        $this->closure = include(ClosureStream::STREAM_PROTO . '://' . $this->code['function']);

        if($this->code['this'] === $this){
            $this->code['this'] = null;
        }

        if ($this->code['scope'] !== null || $this->code['this'] !== null) {
            if($this->code['this'] !== null){
                $this->serializeThis = true;
            }
            $this->closure = $this->closure->bindTo($this->code['this'], $this->code['scope']);
        }

        $this->code = $this->code['function'];
    }

    /**
     * Indicates is closures can be bound to objects
     *
     * @return boolean
     */
    public static function supportBinding()
    {
        if (static::$bindingSupported === null) {
            static::$bindingSupported = method_exists('Closure', 'bindTo');
        }

        return static::$bindingSupported;
    }

    /**
     * Wraps a closure and sets the serialization context (if any)
     *
     * @param   Closure $closure Closure to be wrapped
     * @param   boolean $serializeThis - Deprecated
     *
     * @return  self    The wrapped closure
     */
    public static function from(Closure $closure, $serializeThis = false)
    {
        if (static::$context === null) {
            $instance = new static($closure, $serializeThis);
        } elseif (isset(static::$context->instances[$closure])) {
            $instance = static::$context->instances[$closure];
            $instance->serializeThis = $serializeThis;
        } else {
            $instance = new static($closure, $serializeThis);
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
     * Helper method for unserialization
     */
    public static function unserializeData($data)
    {
        if (!static::$unserializations++) {
            static::$deserialized = array();
        }

        $value = unserialize($data);

        if (!--static::$unserializations) {
            static::$deserialized = null;
        }

        return $value;
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
     * Internal method used to unserialize closures in PHP 5.3
     *
     * @param   string &$data Serialized closure
     * @throws SecurityException
     */
    protected function unserializePHP53(&$data)
    {

        if (!static::$unserializations++) {
            static::$deserialized = array();
        }

        $this->code = unserialize($data);

        if(isset($this->code['hash'])){
            if(static::$securityProvider !== null){
                if(!static::$securityProvider->verify($this->code)){
                    throw new SecurityException("Your serialized closure might have been modified and it's unsafe to be unserialized." .
                        "Make sure you are using the same security provider, with the same settings, " .
                        "both for serialization and unserialization.");
                }
                $this->code = unserialize($this->code['closure']);
            } else {
                $this->code = unserialize($this->code['closure']);
            }
        }

        if (isset(static::$deserialized[$this->code['self']->hash])) {
            $this->closure = static::$deserialized[$this->code['self']->hash];
            goto setcode;
        }

        static::$deserialized[$this->code['self']->hash] = null;

        if ($this->code['use']) {
            $this->code['use'] = array_map(array($this, 'mapPointers'), $this->code['use']);
            extract($this->code['use'], EXTR_OVERWRITE | EXTR_REFS);
        }

        $this->closure = include(ClosureStream::STREAM_PROTO . '://' . $this->code['function']);

        static::$deserialized[$this->code['self']->hash] = $this->closure;

        setcode:

        $this->code = $this->code['function'];

        if (!--static::$unserializations) {
            static::$deserialized = null;
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
        } elseif ($value instanceof SelfReference) {
            $pointer = &static::$deserialized[$value->hash];
            return $pointer;
        } elseif (is_array($value)) {
            $pointer = array_map(array($this, __FUNCTION__), $value);
            return $pointer;
        } elseif ($value instanceof \stdClass) {
            $pointer = (array)$value;
            $pointer = array_map(array($this, __FUNCTION__), $pointer);
            $pointer = (object)$pointer;
            return $pointer;
        }
        return $value;
    }

    /**
     * Internal method used to map closures by reference
     *
     * @param   mixed &$value
     *
     * @return  mixed   The mapped values
     */
    protected function &mapByReference(&$value)
    {
        if ($value instanceof Closure) {
            if (isset($this->scope->storage[$value])) {
                if (static::supportBinding()) {
                    $ret = $this->scope->storage[$value];
                } else {
                    $ret = $this->scope->storage[$value]->reference;
                }
                return $ret;
            }

            $instance = new static($value, $this->serializeThis);

            if (static::$context !== null) {
                static::$context->scope->toserialize--;
            } else {
                $instance->scope = $this->scope;
            }

            $this->scope->storage[$value] = $instance;
            return $instance;
        } elseif (is_array($value)) {
            $ret = array_map(array($this, __FUNCTION__), $value);
            return $ret;
        } elseif ($value instanceof \stdClass) {
            $ret = (array)$value;
            $ret = array_map(array($this, __FUNCTION__), $ret);
            $ret = (object)$ret;
            return $ret;
        }
        return $value;
    }
}
