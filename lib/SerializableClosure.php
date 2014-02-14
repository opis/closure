<?php
/* ===========================================================================
 * Opis Project
 * http://opis.io
 * ===========================================================================
 * Copyright (c) 2014 Opis Project
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure;

use Closure;
use Serializable;
use SplObjectStorage;

class SerializableClosure implements Serializable
{
    
    protected $closure;
    
    protected $reflector;
    
    protected $code;
    
    protected $reference;
    
    protected $isBinded = false;
    
    protected $serializeBind = false;
    
    protected $scope;
    
    protected static $context;
    
    protected static $unserializations = 0;
    
    protected static $deserialized;
    
    protected static $bindingSupported;
    
    
    public function __construct(Closure $closure, $serializeBind = false)
    {
        $this->closure = $closure;
        $this->serializeBind = (bool) $serializeBind;
        
        if(static::$context !== null)
        {
            $this->scope = static::$context->scope;
            $this->scope->toserialize++;
        }
    }
    
    protected function &getClosurePointer()
    {
        return $this->closure;
    }
    
    public function getClosure()
    {
        return $this->closure;
    }
    
    public function getReflector()
    {
        if($this->reflector === null)
        {
            $this->reflector = new ReflectionClosure($this->closure, $this->code);
            $this->code = null;
        }
        
        return $this->reflector;
    }
    
    public static function supportBinding()
    {
        if(static::$bindingSupported === null)
        {
            static::$bindingSupported = method_exists('Closure', 'bindTo');
        }
        
        return static::$bindingSupported;
    }
    
    protected function &mapPointers(&$value)
    {
        if($value instanceof static)
        {
            $pointer = &$value->getClosurePointer();
            return $pointer;
        }
        elseif($value instanceof SelfReference)
        {
            $pointer = &static::$deserialized[$value->hash];
            return $pointer;
        }
        elseif(is_array($value))
        {
            $pointer = array_map(array($this, __FUNCTION__), $value);
            return $pointer;
        }
        elseif($value instanceof \stdClass)
        {
            $pointer = (array) $value;
            $pointer = array_map(array($this, __FUNCTION__), $pointer);
            $pointer = (object) $pointer;
            return $pointer;
        }
        return $value;
    }
    
    protected function &mapByReference(&$value)
    {
        
        if($value instanceof Closure)
        {
            if(isset($this->scope->storage[$value]))
            {
                if(static::supportBinding())
                {
                    $ret = $this->scope->storage[$value];
                }
                else
                {
                    $ret = $this->scope->storage[$value]->reference;
                }
                return $ret;
            }
            
            $instance = new static($value, false);
            
            if(static::$context !== null)
            {
                static::$context->scope->toserialize--;
            }
            else
            {
                $instance->scope = $this->scope;
            }
            
            $this->scope->storage[$value] = $instance;
            return $instance;
        }
        elseif(is_array($value))
        {
            $ret = array_map(array($this, __FUNCTION__), $value);
            return $ret;
        }
        elseif($value instanceof \stdClass)
        {
            $ret = (array) $value;
            $ret = array_map(array($this, __FUNCTION__), $ret);
            $ret = (object) $ret;
            return $ret;
        }
        return $value;
    }
    
    
    public function __invoke()
    {
        return $this->isBinded
                    ? call_user_func_array($this->closure, func_get_args())
                    : $this->getReflector()->invokeArgs(func_get_args());
                    
    }
    
    public function serialize()
    {
        if($this->scope === null)
        {
            $this->scope = new ClosureScope();
            $this->scope->toserialize++;
        }
        
        if(!$this->scope->serializations++)
        {
            $this->scope->storage = new SplObjectStorage();
        }
        
        if(!static::supportBinding())
        {
            $this->reference = new SelfReference($this);
        }
        
        $reflector = $this->getReflector();
        $this->scope->storage[$this->closure] = $this;
        $variables = $reflector->getStaticVariables();
        $use = &$this->mapByReference($variables);
        
        $scope = null;
        $that = null;
        
        if($this->serializeBind && static::supportBinding())
        {
            if($scope = $reflector->getClosureScopeClass())
            {
                $scope = $scope->name;
                $that = $reflector->getClosureThis();
            }
        }
        
        $ret = serialize(array(
            'use' => $use,
            'function' => $reflector->getCode(),
            'scope' => $scope,
            'this' => $that,
            'self' => $this->reference,
        ));
        
        
        if(!--$this->scope->serializations && !--$this->scope->toserialize)
        {
            $this->scope->storage = null;
        }
        
        return $ret;
    }
     
    public function unserialize($data)
    {
        ClosureStream::register();
        
        if(!static::supportBinding())
        {
            $this->unserializePHP53($data);
            return;
        }
        
        $this->code = unserialize($data);
        
        $this->code['use'] = array_map(array($this, 'mapPointers'), $this->code['use']);
        
        extract($this->code['use'], EXTR_OVERWRITE | EXTR_REFS);
        
        $this->closure = include(ClosureStream::STREAM_PROTO . '://' . $this->code['function']);
       
        if($this !== $this->code['this'] && ($this->code['scope'] !== null || $this->code['this'] !== null))
        {
            $this->isBinded = $this->serializeBind = true;
            $this->closure = $this->closure->bindTo($this->code['this'], $this->code['scope']);
        }
        
        $this->code = $this->code['function'];
        
    }
    
    protected function unserializePHP53(&$data)
    {
        if(!static::$unserializations++)
        {
            static::$deserialized = array();
        }
        
        $this->code = unserialize($data);
        
        static::$deserialized[$this->code['self']->hash] = null;
        
        
        $this->code['use'] = array_map(array($this, 'mapPointers'), $this->code['use']);
        
        
        extract($this->code['use'], EXTR_OVERWRITE | EXTR_REFS);
        
        $this->closure = include(ClosureStream::STREAM_PROTO . '://' . $this->code['function']);
        
        static::$deserialized[$this->code['self']->hash] = $this->closure;
        
        $this->code = $this->code['function'];
        
        if(!--static::$unserializations)
        {
            static::$deserialized = null;
        }
    }
    
    public static function from(Closure $closure, $serializeThis = false)
    {
        if(static::$context === null)
        {
            $instance = new SerializableClosure($closure, $serializeThis);
        }
        elseif(isset(static::$context->instances[$closure]))
        {
            $instance = static::$context->instances[$closure];
            $instance->serializeBind = $serializeThis;
        }
        else
        {
            $instance = new SerializableClosure($closure, $serializeThis);
            static::$context->instances[$closure] = $instance;
        }
        
        return $instance;
    }
    
    public static function enterContext()
    {
        if(static::$context === null)
        {
            static::$context = new ClosureContext();
        }
        
        static::$context->locks++;
    }
    
    public static function exitContext()
    {
        if(static::$context !== null && !--static::$context->locks)
        {
            static::$context = null;
        }
    }
 
}

class SelfReference
{
    public $hash;
    
    public function __construct($object)
    {
        $this->hash = spl_object_hash($object);
    }
}

class ClosureScope
{
    public $serializations = 0;
    public $toserialize = 0;
    public $storage;
}

class ClosureContext
{
    public $scope;
    
    public $instances;
    
    public $locks;
    
    public function __construct()
    {
        $this->scope = new ClosureScope();
        $this->instances = new SplObjectStorage();
        $this->locks = 0;
    }
}
