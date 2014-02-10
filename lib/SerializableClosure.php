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

class SerializableClosure implements Serializable
{
    
    protected $closure;
    
    protected $reflector;
    
    protected $code;
     
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
    
    protected function getCode()
    {
        if($this->code === null)
        {
            $reflector = $this->getReflector();
            
            $map = function(&$value)
            {
                if($value instanceof Closure)
                {
                    return $value === $this->closure ? $this : new static($value);
                }
                return $value;
            };
            
            $this->code = array(
                'use' => array_map($map, $reflector->getStaticVariables()),
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
        $this->code['use'] = array_map(function(&$value){
            if($value instanceof SerializableClosure)
            {
                if($value === $this)
                {
                    return $this;
                }
                
                return $value->getClosure();
            }
            return $value;
        }, $this->code['use']);
        
        extract($this->code['use']);
        
        $this->closure = include(ClosureStream::STREAM_PROTO . '://' . $this->code['function']);
    }
 
}
