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

        $this->assertEquals($short, $reflector->isShortClosure());
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
     * @dataProvider autoDetectDataProvider
     */
    public function testAutoDetect(Closure $closure, bool $refThis, bool $refScope)
    {
        $reflector = new ReflectionClosure($closure);
        $this->assertEquals($refThis, $reflector->isBindingRequired());
        $this->assertEquals($refScope, $reflector->isScopeRequired());
    }

    public function autoDetectDataProvider(): array
    {
        return [
            [
                fn() => 123,
                false,
                false,
            ],
            [
                fn() => self::class,
                false,
                true,
            ],
            [
                fn() => static::class,
                false,
                true,
            ],
            [
                fn() => parent::method(),
                true,
                true,
            ],
            [
                function () {
                    static $i = 0;
                    return $this;
                },
                true,
                false, // static variable
            ],
            [
                function () {
                    $i = static::class;
                    return $this;
                },
                true,
                true,
            ],
            [
                function () {
                    // anonymous class body should be ignored
                    return new class {
                        public static function cls() {
                            return self::class;
                        }
                        public function me() {
                            return $this;
                        }
                    };
                },
                false,
                false,
            ],
            [
                function () {
                    return new class (self::class) {
                        public static function cls() {
                            return self::class;
                        }
                        public function me() {
                            return $this;
                        }
                    };
                },
                false,
                true, // self::class is passed as arg
            ],
            [
                function () {
                    return new class ($this) {
                        public static function cls() {
                            return self::class;
                        }
                        public function me() {
                            return $this;
                        }
                    };
                },
                true, // $this is passed as arg
                false,
            ],
            [
                function () {
                    return new class ($this, static::$var) {
                        public static function cls() {
                            return self::class;
                        }
                        public function me() {
                            return $this;
                        }
                    };
                },
                true, // $this is passed as arg
                true, // static::$var passed as arg
            ],
// Only php 8
//            [
//                fn($x) : static => null,
//                false,
//                true,
//            ],
            [
                fn(self $x) => null,
                false,
                true,
            ],
            [
                fn($x = self::CONSTANT) => null,
                false,
                true,
            ],
            [
                fn($x = SomeClass::CONSTANT) => null,
                false,
                false,
            ],
        ];
    }
}