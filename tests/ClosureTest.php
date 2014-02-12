<?php
use Opis\Closure\ClosureStream;
use Opis\Closure\ReflectionClosure;
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