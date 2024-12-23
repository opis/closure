<?php

namespace Opis\Closure\Test\PHP83;

use Opis\Closure\Test\SyntaxTestCase;

class SyntaxTest extends SyntaxTestCase
{
    public function closureProvider(): iterable
    {
        yield [
            'Typed constants',
static fn() => new class() {
    public const string x = "abc";
},
                <<<'PHP'
namespace Opis\Closure\Test\PHP83;
return static fn() => new class() {
    public const string x = "abc";
};
PHP,
        ];
        // readonly classes were added in php 8.2, but readonly anonymous classes are supported only from php 8.3?
        yield [
            'Test readonly anonymous class',
            static fn() => new #[XAttr()] readonly class(){},
                <<<'PHP'
namespace Opis\Closure\Test\PHP83;
return static fn() => new #[XAttr()] readonly class(){};
PHP,
        ];
    }
}