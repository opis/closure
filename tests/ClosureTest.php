<?php
/* ===========================================================================
 * Opis Project
 * http://opis.io
 * ===========================================================================
 * Copyright (c) 2014-2015 Opis Project
 * 
 * Licensed under the MIT License
 * =========================================================================== */

use Opis\Closure\SerializableClosure;

class ClosureTest extends PHPUnit_Framework_TestCase
{
    
    protected function s($closure, $binded = false)
    {
        if($closure instanceof \Closure)
        {
            $closure = new SerializableClosure($closure, $binded);
        }
        return unserialize(serialize($closure));
    }
    
    protected function r() {
        return version_compare(PHP_VERSION, '5.4', '<');
    }

    public function testClosureUseReturnValue()
    {
        $a = 100;
        $c = function() use($a)
        {
            return $a;
        };
        
        $u = $this->s($c);
        
        $this->assertEquals($u(), $a);
    }
    
    public function testClosureUseReturnClosure()
    {
        $a = function($p){
            return $p + 1;
        };
        $b = function($p) use($a){
            return $a($p);
        };
        
        $v = 1;
        $u = $this->s($b);
        
        $this->assertEquals($v + 1, $u(1));
    }
    
    public function testClosureUseReturnClosureByRef()
    {
        $a = function($p){
            return $p + 1;
        };
        $b = function($p) use(&$a){
            return $a($p);
        };
        
        $v = 1;
        $u = $this->s($b);
        
        $this->assertEquals($v + 1, $u(1));
    }
    
    public function testClosureUseSelf()
    {
        
        $a = function() use (&$a){
            return $a;
        };
        $u = $this->s($a);
        
        $this->assertEquals($u->getClosure(), $u());
    }
    
    public function testClosureUseSelfInArray()
    {
        
        $a = array();
        
        $b = function() use(&$a){
            return $a[0];
        };
        
        $a[] = $b;
        
        $u = $this->s($b);
        
        $this->assertEquals($u->getClosure(), $u());
    }
    
    public function testClosureUseSelfInObject()
    {
        
        $a = new stdClass();
        
        $b = function() use(&$a){
            return $a->me;
        };
        
        $a->me = $b;
        
        $u = $this->s($b);
        
        $this->assertEquals($u->getClosure(), $u());
    }
    
    public function testClosureUseSelfInMultiArray()
    {
        
        $a = array();
        $x = null;
        
        $b = function() use(&$x){
            return $x;
        };
        
        $c = function($i) use (&$a) {
            $f = $a[$i];
            return $f();
        };
        
        $a[] = $b;
        $a[] = $c;
        $x = $c;
        
        $u = $this->s($c);
        
        $this->assertEquals($u->getClosure(), $u(0));
    }
    
    public function testClosureBindToObject()
    {
        if($this->r())
        {
            $this->markTestSkipped('This test requires PHP >=5.4');
            return;
        }
        
        $a = new A();
        
        $b = function(){
          return $this->aPublic();
        };
        
        $b = $b->bindTo($a);
        
        $u = $this->s($b, true);
        
        $this->assertEquals('public called', $u());
    }
    
    public function testClosureBindToObjectScope()
    {
        if($this->r())
        {
            $this->markTestSkipped('This test requires PHP >=5.4');
            return;
        }
        
        $a = new A();
        
        $b = function(){
          return $this->aProtected();
        };
        
        $b = $b->bindTo($a, 'A');
        
        $u = $this->s($b, true);
        
        $this->assertEquals('protected called', $u());
    }
    
    public function testClosureBindToObjectStaticScope()
    {
        if($this->r())
        {
            $this->markTestSkipped('This test requires PHP >=5.4');
            return;
        }
        
        $a = new A();
        
        $b = function(){
          return static::aStaticProtected();
        };
        
        $b = $b->bindTo(null, 'A');
        
        $u = $this->s($b, true);
        
        $this->assertEquals('static protected called', $u());
    }
    
    public function testClosureSerializationTwice()
    {
        $a = function($p){
          return $p;  
        };
        
        $b = function($p) use($a){
            return $a($p);
        };
        
        $u = $this->s($this->s($b));
        
        $this->assertEquals('ok', $u('ok'));
    }
    
    public function testClosureRealSerialization()
    {
        $f = function($a, $b){
            return $a + $b;
        };
        
        $u = $this->s($this->s($f)->getClosure());
        $this->assertEquals(5, $u(2, 3));
    }
    
    public function testClosureObjectinObject()
    {
        $f = function() use (&$f) {
          return $f;
        };
        
        $t = new ObjnObj();
        $t->func = $f;
        
        $t2 = new ObjnObj();
        $t2->func = $f;
        
        $t->subtest = $t2;
        
        $x = unserialize(serialize($t));
        
        $g = $x->func;
        $g = $g();
        
        $ok = $x->func == $x->subtest->func;
        $ok = $ok && ($x->subtest->func == $g);
        
        $this->assertEquals(true, $ok);
    }
    
    public function testClosureNested()
    {
        $o = function($a) {
            
            // this should never happen
            if ($a === false) {
                return false;
            }
            
            $n = function ($b) {
                return !$b;
            };
            $ns = unserialize(serialize(new Opis\Closure\SerializableClosure($n)));
            
            return $ns(false);
        };
        
        $os = $this->s($o);

        $this->assertEquals(true, $os(true));
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

class ObjnObj implements Serializable {
  public $subtest;
  public $func;
  
  public function serialize() {
    
    SerializableClosure::enterContext();
    
    $object = serialize(array(
        'subtest' => $this->subtest,
        'func' => SerializableClosure::from($this->func),
    ));
    
    SerializableClosure::exitContext();
    
    return $object;
  }
  
  public function unserialize($data) {
    
    $data = SerializableClosure::unserializeData($data);
    
    $this->subtest = $data['subtest'];
    $this->func = $data['func']->getClosure();
  }
  
}