<?php

namespace Opis\Closure\Test;

use Closure;
use Opis\Closure\SerializableClosure;
use Opis\Closure\Test\Fixtures\TestUnitEnum;
use Opis\Closure\Test\Fixtures\TestBackedEnum;

final class EnumTest extends \PHPUnit\Framework\TestCase
{
    public function testSerializesUnitEnum()
    {
        $enum = TestUnitEnum::Test;

        $closure = function () use ($enum) {
            $this->assertEquals(TestUnitEnum::Test, $enum);
        };

        $executable = $this->s($closure);

        $executable();
    }

    public function testSerializesBackedEnum()
    {
        $enum = TestBackedEnum::Test;

        $closure = function () use ($enum) {
            $this->assertEquals(TestBackedEnum::Test, $enum);
        };

        $executable = $this->s($closure);

        $executable();
    }

    public function testSerializesUnitEnumUsingFunctions()
    {
        $expected = TestUnitEnum::Test;

        $actual = \Opis\Closure\unserialize(\Opis\Closure\serialize($expected));

        $this->assertEquals($expected, $actual);

    }

    public function testSerializesBackedEnumUsingFunctions()
    {
        $expected = TestBackedEnum::Test;

        $actual = \Opis\Closure\unserialize(\Opis\Closure\serialize($expected));

        $this->assertEquals($expected, $actual);
    }

    protected function s($closure)
    {
        if ($closure instanceof Closure) {
            $closure = new SerializableClosure($closure);
        }

        return unserialize(serialize($closure))->getClosure();
    }
}
