<?php
/* ===========================================================================
 * Copyright 2020 Zindex Software
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ============================================================================ */

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
            [
                Closure::fromCallable('\str_replace'),
                'x1x2x3',
                ['a', 'x', 'a1a2a3'],
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