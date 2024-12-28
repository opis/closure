<?php

namespace Opis\Closure\Test\PHP80;

use ArrayObject, SplObjectStorage;
use Opis\Closure\Test\SerializeTestCase;

class SplTest extends SerializeTestCase
{
    public function testArrayObject()
    {
        $obj = new ArrayObject(["fn" => static fn () => "ok_key"], ArrayObject::STD_PROP_LIST);
        $obj->fn = static fn() => "ok_prop";
        $obj = $this->process($obj);
        $this->assertEquals("ok_key", $obj["fn"]());
        $this->assertEquals("ok_prop", ($obj->fn)());
    }

    public function testSplObjectStorage()
    {
        $f = static fn () => "ok";
        $obj = new SplObjectStorage();
        $obj[$f] = $f;

        $obj = $this->process($obj);

        $closure = iterator_to_array($obj)[0];

        $this->assertEquals("ok", $closure());
        $this->assertEquals($closure, $obj[$closure]);
    }
}
