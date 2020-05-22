<?php

namespace Opis\Closure\Test;

use Closure;
use Opis\Closure\ReflectionClosure;
use PHPUnit\Framework\TestCase;

// Fake
use Foo\{
    Bar as Baz,
    Baz\Qux
};

class ReflectionTest extends TestCase
{
    /**
     * @dataProvider isBindingRequiredDataProvider
     */
    public function testThisInsideAnonymousClass(Closure $closure, bool $expect)
    {
        $this->assertEquals($expect, (new ReflectionClosure($closure))->isBindingRequired());
    }

    public function isBindingRequiredDataProvider(): iterable
    {
        return [
            [
                function() {
                    return new class {
                        function a(){
                            $self = $this;
                        }
                    };
                },
                false,
            ],
            [
                function () {
                    return new class {
                        function a(){
                            $self = $this;
                            return new class {
                                function a(){
                                    $self = $this;
                                }
                            };
                        }
                    };
                },
                false,
            ],
            [
                function () {
                    $self = $this;
                    return new class {
                        function a(){
                            $self = $this;
                        }
                    };
                },
                true,
            ],
            [
                function () {
                    return new class {
                        function a(){
                            $self = $this;
                        }
                    };
                    $self = $this;
                },
                true,
            ],
        ];
    }


    public function testIsShortClosure()
    {
        $f1 = fn() => 1;
        $f2 = static fn() => 1;
        $f3 = function () { fn() => 1; };

        $this->assertTrue((new ReflectionClosure($f1))->isShortClosure());
        $this->assertTrue((new ReflectionClosure($f2))->isShortClosure());
        $this->assertFalse((new ReflectionClosure($f3))->isShortClosure());
    }
}