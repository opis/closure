<?php declare(strict_types=1);

namespace Opis\Closure\Test;

use Closure;
use Opis\Closure\ReflectionClosure;

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
        $e1 = 'fn() : string|int|false|Bar|null => 1';

        $f2 = fn() : Foo|Bar => 1;
        $e2 = 'fn() : Foo|Bar => 1';

        $f3 = fn() : false => false;
        $e3 = 'fn() : false => false';

        $this->assertEquals($e1, $this->c($f1));
        $this->assertEquals($e2, $this->c($f2));
        $this->assertEquals($e3, $this->c($f3));
    }

    public function testMixedType()
    {
        $f1 = function () : mixed { return 42;};
        $e1 = 'function () : mixed { return 42;}';

        $this->assertEquals($e1, $this->c($f1));
    }
}
