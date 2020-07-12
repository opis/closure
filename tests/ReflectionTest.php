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
use Opis\Closure\ReflectionClosure;
use Opis\Closure\Test\Stub\Object2;
use PHPUnit\Framework\TestCase;

// Used for test
function testFunc () {}

class ReflectionTest extends TestCase
{

    /**
     * @dataProvider closureDataProvider
     */
    public function testReflection(Closure $closure, bool $short, bool $static, array $use)
    {
        $reflector = new ReflectionClosure($closure);

        $this->assertEquals($short, $reflector->isShort());
        $this->assertEquals($static, $reflector->isStatic());
        $this->assertEquals($use, $reflector->getUseVariableNames());
    }

    public function closureDataProvider(): array
    {
        $v1 = $v2 = 1;

        return [
            [
                function () {},
                false, false, [],
            ],
            [
                static function () {},
                false, true, [],
            ],
            [
                function () use ($v1, $v2) {},
                false, false, ['v1', 'v2'],
            ],
            [
                fn () => 1 + 2,
                true, false, [],
            ],
            [
                static fn () => 1 + 2,
                true, true, [],
            ],
            [
                fn () => $v1 + $v2,
                true, false, ['v1', 'v2'],
            ],
        ];
    }

    /**
     * @dataProvider sourceDataProvider
     */
    public function testSource(Closure $closure, bool $callable, bool $method, bool $function, bool $internal)
    {
        $reflector = new ReflectionClosure($closure);

        $this->assertEquals($callable, $reflector->isFromCallable());
        $this->assertEquals($method, $reflector->isClassMethod());
        $this->assertEquals($function, $reflector->isFunction());
        $this->assertEquals($internal, $reflector->isInternal());
    }

    public function sourceDataProvider(): array
    {
        return [
            [
                function () {},
                false, false, false, false,
            ],
            [
                Closure::fromCallable([$this, __FUNCTION__]),
                true, true, false, false,
            ],
            [
                Closure::fromCallable(__NAMESPACE__ . '\\testFunc'),
                true, false, true, false,
            ],
            [
                Closure::fromCallable('str_replace'),
                true, false, true, true,
            ],
        ];
    }

    /**
     * @dataProvider codeClosureProvider()
     */
    public function testGetCode(Closure $closure, string $expectedCode): void
    {
        $ref = new ReflectionClosure($closure);

        $this->assertEquals($expectedCode, $ref->getCode());
    }

    public function codeClosureProvider(): array
    {
        // @formatter:off
        return [
            [
                fn() => 1,
                'fn() => 1',
            ],
            [
                fn () =>  1,
                'fn () =>  1',
            ],
            [
                fn (Stub\Object1 $param): Object2 => new Stub\Object2(),
                'fn (Stub\Object1 $param): Object2 => new Stub\Object2()',
            ],
        ];
        // @formatter:on
    }

}
