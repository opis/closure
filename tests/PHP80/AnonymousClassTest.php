<?php

namespace Opis\Closure\Test\PHP80;

use Opis\Closure\Test\PHP80\Objects\Clone1;
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

    public function testComplex()
    {
        $factory = fn(?Objects\Entity $parent) => new class($parent) extends Objects\Entity {
            public function __construct(?Objects\Entity $parent)
            {
                $this->parent = $parent;
            }
        };

        $a = $factory(null);
        $b = $factory($a);

        // process twice
        $u = $this->process([$a, $b]);
        $u = $this->process($u);

        $this->assertInstanceOf(Objects\Entity::class, $u[0]);
        $this->assertInstanceOf(Objects\Entity::class, $u[1]);

        $this->assertNull($u[0]->parent);
        $this->assertEquals($u[0], $u[1]->parent);
    }

    public function testBoundClosure()
    {
        $v = new class extends Clone1 {};

        $closure = $this->process($v->create());

        $this->assertEquals(1, $closure());
    }

    public function testScopeOnly()
    {
        /**
         * this is an interesting case
         * we don't have $this bound, but we need the static methods
         * from anonymous class
         */
        $v = new class {
            public static function create()
            {
                return static function () {
                    return self::test();
                };
            }

            public static function test() {
                return "ok";
            }
        };

        $s = $this->s($v::create());
        $this->clearCache(); // clear cache so we don't have info in memory
        $closure = $this->u($s);

        $this->assertEquals("ok", $closure());
    }

    public function testCallable()
    {
        $v = new class {
            public static function test() {
                return "ok";
            }
        };

        $closure = $this->process(\Closure::fromCallable([get_class($v), "test"]));

        $this->assertEquals("ok", $closure());
    }

    public function testTrait()
    {
        $v = new class {
            use Objects\StaticTrait1;
        };

        $closure = $this->process($v::create());
        $this->assertEquals("ok-trait", $closure());
    }

    public function testTraitAlias()
    {
        $v = new class {
            use Objects\StaticTrait1 {
                test as test_trait;
            }

            public static function test()
            {
                return "ok-class";
            }
        };

        $closure = $this->process($v::create());
        $this->assertEquals("ok-class", $closure());
    }
}