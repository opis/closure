<?php

namespace Opis\Closure\Test\PHP81;

use Opis\Closure\Test\SyntaxTestCase;

// Test only

use MyAttr as AttrAlias;
use SomeClass as ClassAlias;

class SyntaxTest extends SyntaxTestCase
{
    public function closureProvider(): iterable
    {
        yield [
            'Test intersection types',
            static fn(ClassAlias & AttrAlias & \Countable & A\B $b): MyAttr&ClassAlias & \Iterator & P\X => false,
            <<<'PHP'
namespace Opis\Closure\Test\PHP81;
use MyAttr as AttrAlias,
    SomeClass as ClassAlias;
return static fn(ClassAlias & AttrAlias & \Countable & A\B $b): MyAttr&ClassAlias & \Iterator & P\X => false;
PHP,
        ];
        yield [
            'Test return never',
            static function (): never { die("never"); },
            <<<'PHP'
namespace Opis\Closure\Test\PHP81;
return static function (): never { die("never"); };
PHP,
        ];
        yield [
            'Enum test',
            MyEnum::CASE1->getClosure(),
            <<<'PHP'
namespace Opis\Closure\Test\PHP81;
return fn() => $this;
PHP,
        ];
        yield [
            'First class callable',
            fn() => [\strlen(...), ["class", "method"](...)],
            <<<'PHP'
namespace Opis\Closure\Test\PHP81;
return fn() => [\strlen(...), ["class", "method"](...)];
PHP,
        ];
    }
}