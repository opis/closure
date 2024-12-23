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
    }
}