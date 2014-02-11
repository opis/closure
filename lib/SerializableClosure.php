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
    
    protected $self = array();
     
    public function __construct(Closure $func)
    {
        $this->closure = $func;
    }
     
    public function getClosure()
    {
        return $this->closure;
    }
    
    protected function getReflector()
    {
        if($this->reflector === null)
        {
            $this->reflector = new ReflectionClosure($this->closure);
        }
        
        return $this->reflector;
    }
    
    protected function mapThat(&$value)
    {
        if($value instanceof static)
        {
            if($value !== $this)
            {
                $value = $value->getClosure();
            }
            elseif(is_array($value) || is_object($value))
            {
                array_walk($value, array($this, 'mapThat'));
            }
        }
    }
    
    protected function mapThis(&$value)
    {
        if($value instanceof static)
        {
            if($value === $this)
            {
                $value = $this->closure;
            }
            else
            {
                $value = $value->getClosure();
            }
        }
        elseif(is_array($value) || is_object($value))
        {
            array_walk($value, array($this, 'mapThis'));
        }
    }
    
    protected function getCode()
    {
        if($this->code === null)
        {
            $reflector = $this->getReflector();
            
            $storage = new SplObjectStorage();
            
            $variables = $reflector->getStaticVariables();
            
            $map = function(&$value) use(&$storage, &$map){
                
                if($value instanceof Closure)
                {
                    if($value === $this->closure)
                    {
                        $value = $this;
                    }
                    elseif(isset($storage[$value]))
                    {
                        $value = $storage[$value];
                    }
                    else
                    {
                        $instance = new static($value);
                        $storage[$value] = $instance;
                        $value = $instance;
                    }
                }
                elseif(is_array($value) || (is_object($value) && !($value instanceof static)))
                {
                    array_walk($value, $map);
                }
                
            };
            
            array_walk($variables, $map);
            
            unset($storage);
            
            $this->code = array(
                'use' => $variables,
                'references' => $reflector->getUseReferences(),
                'function' => $reflector->getCode(),
            );
            
            
        }
        
        return $this->code;
    }
     
    public function __invoke()
    {
        return $this->getReflector()->invokeArgs(func_get_args());
    }
     
    public function serialize()
    {
        return serialize($this->getCode());
    }
     
    public function unserialize($data)
    {
        ClosureStream::register();
        
        $this->code = unserialize($data);
        
        array_walk($this->code['use'], array($this, 'mapThat'));
        
        extract($this->code['use']);
        
        $this->closure = include(ClosureStream::STREAM_PROTO . '://' . $this->code['function']);
        
        foreach($this->code['references'] as $name)
        {
            array_walk(${$name}, array($this, 'mapThis'));
        }
        
    }
 
}
