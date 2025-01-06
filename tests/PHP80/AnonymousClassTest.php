<?php

namespace Opis\Closure\Test\PHP80;

use Opis\Closure\Test\SerializeTestCase;

class AnonymousClassTest extends SerializeTestCase
{
    public function testSimple()
    {
        $v = new class(123) {
            public function __construct(public $value)
            {
            }
        };

        $u = $this->process($v);

        $this->assertEquals(123, $u->value);
    }
}