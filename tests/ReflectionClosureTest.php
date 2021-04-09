<?php
/* ===========================================================================
 * Copyright (c) 2018-2019 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure\Test;

use Closure;
use Opis\Closure\ReflectionClosure;

// Fake
use Foo\Bar;
use Foo\Baz as Qux;

class ReflectionClosureTest extends \PHPUnit\Framework\TestCase
{
    protected function c(Closure $closure)
    {
        $r = new ReflectionClosure($closure);
        return $r->getCode();
    }

    public function testNewInstance()
    {
        $f = function (){ $c = '\A'; new $c;};
        $e = 'function (){ $c = \'\A\'; new $c;}';
        $this->assertEquals($e, $this->c($f));
    }

    public function testNewInstance2()
    {
        $f = function (){ new A; };
        $e = 'function (){ new \Opis\Closure\Test\A; }';
        $this->assertEquals($e, $this->c($f));

        $f = function (){ new A\B; };
        $e = 'function (){ new \Opis\Closure\Test\A\B; }';
        $this->assertEquals($e, $this->c($f));

        $f = function (){ new \A; };
        $e = 'function (){ new \A; }';
        $this->assertEquals($e, $this->c($f));

        $f = function (){ new A(new B, [new C]); };
        $e = 'function (){ new \Opis\Closure\Test\A(new \Opis\Closure\Test\B, [new \Opis\Closure\Test\C]); }';
        $this->assertEquals($e, $this->c($f));

        $f = function (){ new self; new static; new parent; };
        $e = 'function (){ new self; new static; new parent; }';
        $this->assertEquals($e, $this->c($f));
    }

    public function testInstanceOf()
    {
        $f = function (){ $c = null; $b = '\X\y'; v($c instanceof $b);};
        $e = 'function (){ $c = null; $b = \'\X\y\'; v($c instanceof $b);}';
        $this->assertEquals($e, $this->c($f));
    }

    public function testClosureResolveArguments()
    {
        $f1 = function (Bar $p){};
        $e1 = 'function (\Foo\Bar $p){}';

        $f2 = function (Bar\Test $p){};
        $e2 = 'function (\Foo\Bar\Test $p){}';

        $f3 = function (Qux $p){};
        $e3 = 'function (\Foo\Baz $p){}';

        $f4 = function (Qux\Test $p){};
        $e4 = 'function (\Foo\Baz\Test $p){}';

        $f5 = function (\Foo $p){};
        $e5 = 'function (\Foo $p){}';

        $f6 = function (Foo $p){};
        $e6 = 'function (\\' . __NAMESPACE__ . '\Foo $p){}';

        $this->assertEquals($e1, $this->c($f1));
        $this->assertEquals($e2, $this->c($f2));
        $this->assertEquals($e3, $this->c($f3));
        $this->assertEquals($e4, $this->c($f4));
        $this->assertEquals($e5, $this->c($f5));
        $this->assertEquals($e6, $this->c($f6));
    }

    public function testCloureResolveInBody()
    {
        $f1 = function () { return new Bar(); };
        $e1 = 'function () { return new \Foo\Bar(); }';

        $f2 = function () { return new Bar\Test(); };
        $e2 = 'function () { return new \Foo\Bar\Test(); }';

        $f3 = function () { return new Qux(); };
        $e3 = 'function () { return new \Foo\Baz(); }';

        $f4 = function () { return new Qux\Test(); };
        $e4 = 'function () { return new \Foo\Baz\Test(); }';

        $f5 = function () { return new \Foo(); };
        $e5 = 'function () { return new \Foo(); }';

        $f6 = function () { return new Foo(); };
        $e6 = 'function () { return new \\' . __NAMESPACE__ . '\Foo(); }';


        $this->assertEquals($e1, $this->c($f1));
        $this->assertEquals($e2, $this->c($f2));
        $this->assertEquals($e3, $this->c($f3));
        $this->assertEquals($e4, $this->c($f4));
        $this->assertEquals($e5, $this->c($f5));
        $this->assertEquals($e6, $this->c($f6));
    }

    public function testClosureResolveStaticMethod()
    {

        $f1 = function () { return Bar::test(); };
        $e1 = 'function () { return \Foo\Bar::test(); }';

        $f2 = function () { return Bar\Test::test(); };
        $e2 = 'function () { return \Foo\Bar\Test::test(); }';

        $f3 = function () { return Qux::test(); };
        $e3 = 'function () { return \Foo\Baz::test(); }';

        $f4 = function () { return Qux\Test::test(); };
        $e4 = 'function () { return \Foo\Baz\Test::test(); }';

        $f5 = function () { return \Foo::test(); };
        $e5 = 'function () { return \Foo::test(); }';

        $f6 = function () { return Foo::test(); };
        $e6 = 'function () { return \\' . __NAMESPACE__ . '\Foo::test(); }';


        $this->assertEquals($e1, $this->c($f1));
        $this->assertEquals($e2, $this->c($f2));
        $this->assertEquals($e3, $this->c($f3));
        $this->assertEquals($e4, $this->c($f4));
        $this->assertEquals($e5, $this->c($f5));
        $this->assertEquals($e6, $this->c($f6));
    }

    public function testStaticInsideClosure()
    {
        $f1 = function() { return static::foo(); };
        $e1 = 'function() { return static::foo(); }';

        $f2 = function ($a) { return $a instanceof static; };
        $e2 = 'function ($a) { return $a instanceof static; }';

        $this->assertEquals($e1, $this->c($f1));
        $this->assertEquals($e2, $this->c($f2));
    }

    public function testSelfInsideClosure()
    {
        $f1 = function() { return self::foo(); };
        $e1 = 'function() { return self::foo(); }';

        $f2 = function ($a) { return $a instanceof self; };
        $e2 = 'function ($a) { return $a instanceof self; }';

        $this->assertEquals($e1, $this->c($f1));
        $this->assertEquals($e2, $this->c($f2));
    }

    public function testParentInsideClosure()
    {
        $f1 = function() { return parent::foo(); };
        $e1 = 'function() { return parent::foo(); }';

        $f2 = function ($a) { return $a instanceof parent; };
        $e2 = 'function ($a) { return $a instanceof parent; }';

        $this->assertEquals($e1, $this->c($f1));
        $this->assertEquals($e2, $this->c($f2));
    }

    public function testInterpolation1()
    {
        $f1 = function() { return "${foo}${bar}{$foobar}"; };
        $e1 = 'function() { return "${foo}${bar}{$foobar}"; }';

        $this->assertEquals($e1, $this->c($f1));
    }
}