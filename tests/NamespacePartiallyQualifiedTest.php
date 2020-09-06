<?php

namespace Opis\Closure\Test;

use Closure;
use Opis\Closure\ReflectionClosure;
use Foo\Bar;

final class NamespacePartiallyQualifiedTest extends \PHPUnit\Framework\TestCase
{
    public function test_instantiate_partially_qualified_namespace()
    {
        $f = function(Bar\Test $p){};
        $e = 'function(\Foo\Bar\Test $p){}';
        $this->assertEquals($e, $this->c($f));
    }

    protected function c(Closure $closure)
    {
        $r = new ReflectionClosure($closure);

        return $r->getCode();
    }
}
