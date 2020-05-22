<?php

namespace Opis\Closure\Test;

use Closure;
use PHPUnit\Framework\TestCase;

class SerializeTest extends TestCase
{
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


    public function fnDataProvider(): iterable
    {
        $a = 4;
        $use_like = fn(int $b, int $c = 5) : int => ($a + $b) * $c;

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
        ];
    }


    protected function applyTest(Closure $closure, $expected, ?array $args, int $times, ?string $message = null)
    {
        $repeat = $times;
        while ($repeat--) {
            $closure = unserialize(serialize($closure));
        }

        if (!$args) {
            $args = [];
        }

        $this->assertEquals($expected, $closure(...$args), $message . " x {$times}");
    }

}