<?php

namespace Opis\Closure\Test\PHP85;

use Opis\Closure\Test\SerializeTestCase;

class SerializeTest extends SerializeTestCase
{
    public function testPipeOperatorResult()
    {
        $wrap = static function (string $input) {
            return fn() => $input |> strtoupper(...);
        };
        $fn = "Hello" |> $wrap(...);

        $fn2 = $this->process($fn);
        $this->assertEquals($fn2(), "HELLO");
    }

    public function testConstExpression() {
        $src = static function (callable $input = static function() {return 123;}): callable {
            return $input;
        };

        $fn = $src();
        $this->assertEquals($fn(), 123);
    }

    public function testConstExpression2() {
        $src = static fn (callable $input = static function() {return 123;}): callable => $input;

        $fn = $src();
        $this->assertEquals($fn(), 123);
    }
}