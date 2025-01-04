<?php

namespace Opis\Closure\Test\PHP80;

use stdClass, DateTime, Closure;
use Opis\Closure\ReflectionClosure;
use Opis\Closure\Test\SerializeTestCase;
use Opis\Closure\Test\PHP80\Objects\{Abc, ChildClass, Clone1, Entity, ObjSelf, ObjFactory};

class SerializeTest extends SerializeTestCase
{

    public function testSelfReference1()
    {
        $f = static function () use (&$f) {
            return $f;
        };

        $closure = $this->process($f);
        $this->assertEquals($closure, $closure());
    }

    public function testSelfReference2()
    {
        $arr = ["f" => null];
        $f = static function () use (&$arr) {
            return $arr["f"];
        };
        $arr["f"] = $f;

        $closure = $this->process($f);
        $this->assertEquals($closure, $closure());
    }

    public function testSelfReference3()
    {
        $g = null;
        $f = function () use(&$g) {
            return $g;
        };
        $g = function () use (&$f) {
            return $f;
        };

        $arr = $this->process([$f, $g]);
        $this->assertEquals($arr[0], $arr[1]());
        $this->assertEquals($arr[1], $arr[0]());
    }

    public function testRecursiveArray()
    {
        $a = ['foo'];
        $a[] = &$a;
        $f = function () use ($a) {
            return $a[1][0];
        };
        $u = $this->process($f);
        $this->assertEquals('foo', $u());
    }

    public function testRecursiveArray2()
    {
        $a = ['foo'];
        $a[] = &$a;
        $f = function () use (&$a) {
            return $a[1][0];
        };
        $u = $this->process($f);
        $this->assertEquals('foo', $u());
    }

    public function testRecursiveArray3()
    {
        $f = function () {
            return true;
        };
        $a = [$f];
        $a[] = &$a;

        $u = $this->process($a);
        $u = $u[1][0];
        $this->assertTrue($u());
    }

    public function testRecursiveArray4()
    {
        $a = [];
        $f = function () use ($a) {
            return true;
        };
        $a[] = $f;
        $a[] = &$a;

        $u = $this->process($a);
        $u = $u[1][0];
        $this->assertTrue($u());
    }

    public function testRecursiveArray5()
    {
        $a = [];
        $f = function () use (&$a) {
            return true;
        };
        $a[] = $f;
        $a[] = &$a;

        $u = $this->process($a);
        $u = $u[1][0];
        $this->assertTrue($u());
    }

    public function testRecursiveArray6()
    {
        $o = new stdClass();
        $o->a = [];
        $f = function () {
            return true;
        };
        $a = &$o->a;
        $a[] = $f;
        $a[] = &$a;

        $u = $this->process($o);
        $u = $u->a[1][0];
        $this->assertTrue($u());
    }

    public function testRecursiveArray7()
    {
        $o = new stdClass();
        $o->a = [];
        $f = function () use ($o) {
            return true;
        };
        $a = &$o->a;
        $a[] = $f;
        $a[] = &$a;

        $u = $this->process($o);
        $u = $u->a[1][0];
        $this->assertTrue($u());
    }

    public function testClosureUseReturnValue()
    {
        $a = 100;
        $c = function () use ($a) {
            return $a;
        };

        $u = $this->process($c);

        $this->assertEquals($u(), $a);
    }

    public function testClosureUseReturnClosure()
    {
        $a = function ($p) {
            return $p + 1;
        };
        $b = function ($p) use ($a) {
            return $a($p);
        };

        $v = 1;
        $u = $this->process($b);

        $this->assertEquals($v + 1, $u(1));
    }

    public function testClosureUseReturnClosureByRef()
    {
        $a = function ($p) {
            return $p + 1;
        };
        $b = function ($p) use (&$a) {
            return $a($p);
        };

        $v = 1;
        $u = $this->process($b);

        $this->assertEquals($v + 1, $u(1));
    }

    public function testClosureUseSelf()
    {

        $a = function () use (&$a) {
            return $a;
        };
        $u = $this->process($a);

        $this->assertEquals($u, $u());
    }

    public function testClosureUseSelfInArray()
    {

        $a = array();

        $b = function () use (&$a) {
            return $a[0];
        };

        $a[] = $b;

        $u = $this->process($b);

        $this->assertEquals($u, $u());
    }

    public function testClosureUseSelfInObject()
    {

        $a = new stdClass();

        $b = function () use (&$a) {
            return $a->me;
        };

        $a->me = $b;

        $u = $this->process($b);

        $this->assertEquals($u, $u());
    }

    public function testClosureUseSelfInMultiArray()
    {
        $a = array();
        $x = null;

        $b = function () use (&$x) {
            return $x;
        };

        $c = function ($i) use (&$a) {
            $f = $a[$i];
            return $f();
        };

        $a[] = $b;
        $a[] = $c;
        $x = $c;

        $u = $this->process($c);

        $this->assertEquals($u, $u(0));
    }

    public function testClosureUseSelfInInstance()
    {
        $i = new ObjSelf();
        $c = function ($c) use ($i) {
            return $c === $i->closure;
        };
        $i->closure = $c;
        $u = $this->process($c);
        $this->assertTrue($u($u));
    }

    public function testClosureUseSelfInInstance2()
    {
        $i = new ObjSelf();
        $c = function () use (&$c, $i) {
            return $c == $i->closure;
        };
        $i->closure = &$c;
        $u = $this->process($c);
        $this->assertTrue($u());
    }

    public function testCustomSerialization()
    {
        $f = function ($value) {
            return $value;
        };

        $a = new Abc($f);
        $u = $this->process($a);
        $this->assertTrue($u->test(true));
        $this->assertEquals("ok", $u->test("ok"));
    }

    public function testCustomSerializationSameObjects()
    {
        $f = function ($value) {
            return $value;
        };

        $i = new Abc($f);
        $a = [$i, $i];
        $u = $this->process($a);

        $this->assertTrue($u[0] === $u[1]);
    }


    public function testCustomSerializationSameClosures()
    {
        $f = function ($value) {
            return $value;
        };

        $i = new Abc($f);
        $a = [$i, $i];
        $u = $this->process($a);
        $this->assertTrue($u[0]->getF() === $u[1]->getF());
    }

    public function testCustomSerializationSameClosures2()
    {
        $f = function ($value) {
            return $value;
        };

        $a = [new Abc($f), new Abc($f)];
        $u = $this->process($a);
        $this->assertTrue($u[0]->getF() === $u[1]->getF());
    }

    public function testPrivateMethodClone()
    {
        $a = new Clone1();
        $u = $this->process($a);
        $this->assertEquals(1, $u->value());
    }

    public function testPrivateMethodClone2()
    {
        $a = new Clone1();
        $f = function () use ($a) {
            return $a->value();
        };
        $u = $this->process($f);
        $this->assertEquals(1, $u());
    }

    public function testNestedObjects()
    {
        $parent = new Entity();
        $child = new Entity();
        $parent->children[] = $child;
        $child->parent = $parent;

        $f = function () use ($parent, $child) {
            return $parent === $child->parent;
        };

        $u = $this->process($f);
        $this->assertTrue($u());
    }

    public function testNestedObjects2()
    {
        $child = new stdClass();
        $parent = new stdClass();
        $child->parent = $parent;
        $parent->childern = [$child];
        $parent->closure = function () use ($child) {
            return true;
        };
        $u = $this->process($parent)->closure;
        $this->assertTrue($u());
    }

    public function testNestedObjects3()
    {
        $obj = new stdClass();
        $obj->closure = function ($arg) use ($obj) {
            return $arg === $obj;
        };

        $u = $this->process($obj);
        $c = $u->closure;
        $this->assertTrue($c($u));
    }

    public function testNestedObjects4()
    {
        $parent = new stdClass();
        $child1 = new stdClass();

        $child1->parent = $parent;

        $parent->closure = function ($p) use ($child1) {
            return $child1->parent === $p;
        };

        $u = $this->process($parent);
        $c = $u->closure;
        $this->assertTrue($c($u));
    }

    public function testNestedObjects5()
    {
        $parent = new stdClass();
        $child1 = new stdClass();
        $child2 = new stdClass();

        $child1->parent = $parent;
        $child2->parent = $parent;

        $parent->closure = function ($p) use ($child1, $child2) {
            return $child1->parent === $child2->parent && $child1->parent === $p;
        };

        $u = $this->process($parent);
        $c = $u->closure;
        $this->assertTrue($c($u));
    }

    public function testPrivatePropertyInParentClass()
    {
        $instance = new ChildClass();

        $closure = function () use ($instance) {
            return $instance->getFoobar();
        };

        $u = $this->process($closure);
        $this->assertSame(['test'], $u());
    }

    public function testInternalClass1()
    {
        $date = new DateTime();
        $date->setDate(2018, 2, 23);

        $closure = function () use ($date) {
            return $date->format('Y-m-d');
        };

        $u = $this->process($closure);
        $this->assertEquals('2018-02-23', $u());
    }

    public function testInternalClass2()
    {
        $date = new DateTime();
        $date->setDate(2018, 2, 23);
        $instance = (object)['date' => $date];
        $closure = function () use ($instance) {
            return $instance->date->format('Y-m-d');
        };

        $u = $this->process($closure);
        $this->assertEquals('2018-02-23', $u());
    }

    public function testInternalClass3()
    {
        $date = new DateTime();
        $date->setDate(2018, 2, 23);
        $instance = (object)['date' => $date];

        $u = $this->process($instance);
        $this->assertEquals('2018-02-23', $u->date->format('Y-m-d'));
    }

    public function testFactoryObj()
    {
        $data = [new ObjFactory(1), new ObjFactory(2)];
        /** @var ObjFactory $u1 */
        /** @var ObjFactory $u2 */
        [$u1, $u2] = $this->process($data);

        $this->assertEquals(1, $u1->getValue());
        $this->assertEquals(2, $u2->getValue());

        // must have the same info
        $this->assertTrue((new ReflectionClosure($u1->factory))->info() === (new ReflectionClosure($u2->factory))->info());
    }

    /**
     * @dataProvider fnDataProvider
     */
    public function testSerialization1(Closure $closure, $expected, array $args = null)
    {
        $this->applyTest($closure, $expected, $args, 1);
    }

    /**
     * @dataProvider fnDataProvider
     */
    public function testSerialization2(Closure $closure, $expected, array $args = null)
    {
        $this->applyTest($closure, $expected, $args, 2);
    }

    protected function applyTest(Closure $closure, $expected, ?array $args, int $times, ?string $message = null)
    {
        $repeat = $times;
        while ($repeat--) {
            $closure = $this->process($closure);
        }

        if (!$args) {
            $args = [];
        }

        $this->assertEquals($expected, $closure(...$args), $message . " x {$times}");
    }

    public function fnDataProvider(): iterable
    {
        $a = 4;
        $use_like = fn(int $b, int $c = 5): int => ($a + $b) * $c;

        return [
            [
                fn() => 'hello',
                'hello',
            ],
            [
                fn($a, $b) => $a + $b,
                7,
                [4, 3],
            ],
            [
                $use_like,
                40,
                [4],
            ],
            [
                $use_like,
                48,
                [4, 6],
            ],
            [
                Closure::fromCallable('\str_replace'),
                'x1x2x3',
                ['a', 'x', 'a1a2a3'],
            ],
        ];
    }
}