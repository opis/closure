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

    public function testNewInstance()
    {
        $f = function (){ $c = '\A'; new $c;};
        $e = 'function (){ $c = \'\A\'; new $c;}';
        $this->assertEquals($e, $this->c($f));
    }

    public function testInstanceOf()
    {
        $f = function (){ $c = null; $b = '\X\y'; v($c instanceof $b);};
        $e = 'function (){ $c = null; $b = \'\X\y\'; v($c instanceof $b);}';
        $this->assertEquals($e, $this->c($f));
    }

    /**
     * @dataProvider closureArgumentsDataProvider
     */
    public function testClosureResolveArguments(Closure $closure, string $code)
    {
        $this->assertEquals($code, $this->c($closure));
    }

    public function closureArgumentsDataProvider()
    {
        return [
            [
                function (Bar $p){},
                'function (\Foo\Bar $p){}',
            ],
            [
                function (Bar\Test $p){},
                'function (\Foo\Bar\Test $p){}',
            ],
            [
                function (Qux $p){},
                'function (\Foo\Baz $p){}',
            ],
            [
                function (Qux\Test $p){},
                'function (\Foo\Baz\Test $p){}',
            ],
            [
                function (\Foo $p){},
                'function (\Foo $p){}',
            ],
            [
                function (Foo $p){},
                'function (\\' . __NAMESPACE__ . '\Foo $p){}',
            ],
            [
                function (iterable $a){},
                'function (iterable $a){}'
            ]
        ];
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
}