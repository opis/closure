<?php

namespace Opis\Closure\Test;

use Closure;
use Opis\Closure\ClosureContext;
use Opis\Closure\ClosureContext as SomeAlias;
use Opis\Closure\SerializableClosure;

final class NamespaceTest extends \PHPUnit\Framework\TestCase
{
    public function testNamespacedObjectInsideClosure()
    {
        $closure = function () {
            $object = new ClosureContext();

            self::assertInstanceOf('\Opis\Closure\ClosureContext', $object);
            self::assertInstanceOf(SomeAlias::class, $object);
        };

        $executable = $this->s($closure);

        $executable();
    }

    protected function s($closure)
    {
        if ($closure instanceof Closure) {
            $closure = new SerializableClosure($closure);
        }

        return unserialize(serialize($closure))->getClosure();
    }
}
