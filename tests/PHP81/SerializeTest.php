<?php

namespace Opis\Closure\Test\PHP81;

use Opis\Closure\Test\SerializeTestCase;

class SerializeTest extends SerializeTestCase
{
    public function testReadonlyObject()
    {
        /** @var ReadonlyPropertyClass $object */
        $object = $this->process(new ReadonlyPropertyClass());
        $this->assertEquals($object, ($object->func)());
    }

    public function testClosureFromReadonlyObject()
    {
        $closure = $this->process((new ReadonlyPropertyClass())->func);
        $this->assertEquals($closure, $closure()->func);
    }

    public function testEnum()
    {
        $closure = $this->process(MyEnum::CASE1->getClosure());
        $this->assertEquals(MyEnum::CASE1, $closure());
    }

    public function testFirstClassCallable()
    {
        $closure = $this->process((new MyInt(5))->read(...));
        $this->assertEquals(5, $closure());
    }
}