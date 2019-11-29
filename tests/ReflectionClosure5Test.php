<?php
/* ===========================================================================
 * Copyright (c) 2018-2019 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure\Test;

use Closure;
use Opis\Closure\ReflectionClosure;
use Foo\{
    Bar as Baz,
    Baz\Qux
};
use Opis\Closure\SerializableClosure;

class ReflectionClosure5Test extends \PHPUnit\Framework\TestCase
{
    protected function c(Closure $closure)
    {
        $r = new ReflectionClosure($closure);
        return $r->getCode();
    }

    protected function s(Closure $closure)
    {
        return unserialize(serialize(new SerializableClosure($closure)))->getClosure();
    }

    public function testBasicShortClosure()
    {
        $f1 = fn() => "hello";
        $e1 = 'fn() => "hello";';

        $f2 = fn&() => "hello";
        $e2 = 'fn&() => "hello";';

        $f3 = fn($a) => "hello";
        $e3 = 'fn($a) => "hello";';

        $f4 = fn(&$a) => "hello";
        $e4 = 'fn(&$a) => "hello";';

        $f5 = fn(&$a) : string => "hello";
        $e5 = 'fn(&$a) : string => "hello";';

        $this->assertEquals($e1, $this->c($f1));
        $this->assertEquals($e2, $this->c($f2));
        $this->assertEquals($e3, $this->c($f3));
        $this->assertEquals($e4, $this->c($f4));
        $this->assertEquals($e5, $this->c($f5));
    }

    public function testResolveTypes()
    {
        $f1 = fn(Baz $a) => "hello";
        $e1 = 'fn(\Foo\Bar $a) => "hello";';

        $f2 = fn(Baz $a) : Qux => "hello";
        $e2 = 'fn(\Foo\Bar $a) : \Foo\Baz\Qux => "hello";';

        $this->assertEquals($e1, $this->c($f1));
        $this->assertEquals($e2, $this->c($f2));
    }

    public function testSerialize()
    {
        $f1 = fn() => 'hello';
        $c1 = $this->s($f1);

        $f2 = fn($a, $b) => $a + $b;
        $c2 = $this->s($f2);

        $a = 4;
        $f3 = fn(int $b, int $c = 5) : int => ($a + $b) * $c;
        $c3 = $this->s($f3);

        $this->assertEquals('hello', $c1());
        $this->assertEquals(7, $c2(4, 3));
        $this->assertEquals(40, $c3(4));
        $this->assertEquals(48, $c3(4, 6));
    }
}