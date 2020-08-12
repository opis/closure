<?php declare(strict_types=1);

namespace Opis\Closure\Test;

use Closure;
use Opis\Closure\ReflectionClosure;

// Fake
use Some\ClassName as ClassAlias;

final class ReflectionClosure6Test extends \PHPUnit\Framework\TestCase
{
    protected function c(Closure $closure)
    {
        $r = new ReflectionClosure($closure);
        return $r->getCode();
    }

    public function testUnionTypes()
    {
        $f1 = fn() : string|int|false|Bar|null => 1;
        $e1 = 'fn() : string|int|false|\Opis\Closure\Test\Bar|null => 1';

        $f2 = fn() : \Foo|\Bar => 1;
        $e2 = 'fn() : \Foo|\Bar => 1';

        $f3 = fn() : int|false => false;
        $e3 = 'fn() : int|false => false';

        $f4 = function () : null | MyClass | ClassAlias | Relative\Ns\ClassName | \Absolute\Ns\ClassName {return null;};
        $e4 = 'function () : null | \Opis\Closure\Test\MyClass | \Some\ClassName | \Opis\Closure\Test\Relative\Ns\ClassName | \Absolute\Ns\ClassName {return null;}';

        $this->assertEquals($e1, $this->c($f1));
        $this->assertEquals($e2, $this->c($f2));
        $this->assertEquals($e3, $this->c($f3));
        $this->assertEquals($e4, $this->c($f4));

        self::assertTrue(true);
    }

    public function testMixedType()
    {
        $f1 = function () : mixed { return 42;};
        $e1 = 'function () : mixed { return 42;}';

        $this->assertEquals($e1, $this->c($f1));
    }
}
