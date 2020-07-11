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
        $f1 = fn() : string|int => 1;
        $e1 = 'fn() : string|int => 1';

        $this->assertEquals($e1, $this->c($f1));
    }

    public function testMixedType()
    {
        $f1 = function () : mixed { return 42;};
        $e1 = 'function () : mixed { return 42;}';

        $this->assertEquals($e1, $this->c($f1));
    }
}
