<?php

namespace Opis\Closure\Test;

use Closure;
use Opis\Closure\ReflectionClosure;
use Foo\{
    Bar as Baz,
    Baz\Qux\Forest
};

final class NamespaceFullyQualifiedTest extends \PHPUnit\Framework\TestCase
{
    public function test_namespace_fully_qualified()
    {
        $f = fn(): Forest => new Forest;
        $e = 'fn(): \Foo\Baz\Qux\Forest => new \Foo\Baz\Qux\Forest';
        $this->assertEquals($e, $this->c($f));
    }

    protected function c(Closure $closure)
    {
        $r = new ReflectionClosure($closure);

        return $r->getCode();
    }
}
