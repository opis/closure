<?php
/* ===========================================================================
 * Opis Project
 * http://opis.io
 * ===========================================================================
 * Copyright (c) 2014-2015 Opis Project
 * 
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Colibri\Test;

use Opis\Closure\ReflectionClosure;

class ClosureTest extends CommonTest
{
    
    public function testClosureBindToObject()
    {
        $a = new A();
        
        $b = function(){
          return $this->aPublic();
        };
        
        $b = $b->bindTo($a, __NAMESPACE__ . "\\A");
        
        $u = $this->s($b);
        
        $this->assertEquals('public called', $u());
    }
    
    public function testClosureBindToObjectScope()
    {
        $a = new A();
        
        $b = function(){
          return $this->aProtected();
        };
        
        $b = $b->bindTo($a, __NAMESPACE__ . "\\A");
        
        $u = $this->s($b);
        
        $this->assertEquals('protected called', $u());
    }
    
    public function testClosureBindToObjectStaticScope()
    {
        $a = new A();
        
        $b = function(){
          return static::aStaticProtected();
        };
        
        $b = $b->bindTo(null, __NAMESPACE__ . "\\A");
        
        $u = $this->s($b);
        
        $this->assertEquals('static protected called', $u());
    }


    public function testClosureStatic()
    {
        $f = static function(){};
        $rc = new ReflectionClosure($f);
        $this->assertTrue($rc->isStatic());
    }

    public function testClosureStaticFail()
    {
        $f = static
            // This will not work
        function(){};
        $rc = new ReflectionClosure($f);
        $this->assertFalse($rc->isStatic());
    }
}

class A
{
    protected static function aStaticProtected() {
        return 'static protected called';
    }
    
    protected function aProtected()
    {
        return 'protected called';
    }
    
    public function aPublic()
    {
        return 'public called';
    }
}