<?php

namespace Opis\Closure\Test\PHP80;

use Opis\Closure\Test\SyntaxTestCase;

// Test only

use MyAttr as AttrAlias;
use SomeClass as ClassAlias;
use Opis\Closure as OpisClosure;
use const A1 as Y;
use const A2 as Z;

class SyntaxTest extends SyntaxTestCase
{
    public function closureProvider(): array
    {
        return [
            [
                'Test union types',
                static fn(int | string $a, ClassAlias | AttrAlias | null $b): int | false | null => false,
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
use MyAttr as AttrAlias,
    SomeClass as ClassAlias;
return static fn(int | string $a, ClassAlias | AttrAlias | null $b): int | false | null => false;
PHP,
            ],
            [
                'Test PHP attributes',
                #[MyAttr] /*comment*/ #[AttrAlias(1, ClassAlias::class, namespace\Other::class)] static fn(#[MyAttr('param')] int $i) => 1,
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
use MyAttr as AttrAlias,
    SomeClass as ClassAlias;
return #[MyAttr] /*comment*/ #[AttrAlias(1, ClassAlias::class, namespace\Other::class)] static fn(#[MyAttr('param')] int $i) => 1;
PHP,
            ],
            [
                'Test ctor',
static function() {
    return new #[MyAttr] class(1, null) {
        #[AttrAlias(1, 2)]
        public function __construct(public int | string $a, private ?ClassAlias $b, )
        {
        }
    };
},
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
use MyAttr as AttrAlias,
    SomeClass as ClassAlias;
return static function() {
    return new #[MyAttr] class(1, null) {
        #[AttrAlias(1, 2)]
        public function __construct(public int | string $a, private ?ClassAlias $b, )
        {
        }
    };
};
PHP,
            ],
            [
                'Named args long function',
                static function() { return named_func(AttrAlias: null, named2: ClassAlias::class); },
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
use SomeClass as ClassAlias;
return static function() { return named_func(AttrAlias: null, named2: ClassAlias::class); };
PHP,
            ],
            [
                'Named args long function 2',
                static function() { return named_func(AttrAlias: new AttrAlias(), named2: ClassAlias::class); },
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
use MyAttr as AttrAlias,
    SomeClass as ClassAlias;
return static function() { return named_func(AttrAlias: new AttrAlias(), named2: ClassAlias::class); };
PHP,
            ],
            [
                'Named args long function with goto',
static function() {
    AttrAlias:
    if (named_func(AttrAlias: 2)) {
        goto AttrAlias;
    }
    return new ClassAlias();
},
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
use SomeClass as ClassAlias;
return static function() {
    AttrAlias:
    if (named_func(AttrAlias: 2)) {
        goto AttrAlias;
    }
    return new ClassAlias();
};
PHP,
            ],
            [
                'Named args',
                static fn() => named_func(name1: 1, name2: [1, 2, 3]),
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return static fn() => named_func(name1: 1, name2: [1, 2, 3]);
PHP,
            ],
            [
                'Named args conflicting with alias',
                static fn() => named_func(AttrAlias: 1, name2: [1, 2, 3]),
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return static fn() => named_func(AttrAlias: 1, name2: [1, 2, 3]);
PHP,
            ],
            [
                'Named args conflicting with alias 2',
                static fn() => named_func(AttrAlias: new AttrAlias(), name2: [1, 2, 3]),
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
use MyAttr as AttrAlias;
return static fn() => named_func(AttrAlias: new AttrAlias(), name2: [1, 2, 3]);
PHP,
            ],
            [
                'Named args inside ternary',
                true ? static fn() => named_func(AttrAlias: 1, name2: [1, 2, 3]) : null,
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return static fn() => named_func(AttrAlias: 1, name2: [1, 2, 3]) ;
PHP,
            ],
            [
                'Named args complex ternary',
                true ? static fn() => true ? named_func(AttrAlias: 1, name2: [1, 2, 3]) : false : null,
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return static fn() => true ? named_func(AttrAlias: 1, name2: [1, 2, 3]) : false ;
PHP,
            ],
            [
                'Named args complex ternary 2',
                true ? static fn() => (true ? named_func(AttrAlias: 1, name2: [1, 2, 3]) : false) : null,
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return static fn() => (true ? named_func(AttrAlias: 1, name2: [1, 2, 3]) : false) ;
PHP,
            ],
            [
                'Named args complex ternary 3',
                true ? static fn() => true ? named_func(AttrAlias: named(x: 1, y: 2), name2: (named(a: 1) ? named(b: 1) : named(c: 2)) ? 1 : 0) : false : null,
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return static fn() => true ? named_func(AttrAlias: named(x: 1, y: 2), name2: (named(a: 1) ? named(b: 1) : named(c: 2)) ? 1 : 0) : false ;
PHP,
            ],
            [
                'Test ns alias',
                static fn(OpisClosure\A $a, OpisClosure\B $b, Other\C $c): int => 0,
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
use Opis\Closure as OpisClosure;
return static fn(OpisClosure\A $a, OpisClosure\B $b, Other\C $c): int => 0;
PHP,
            ],
            [
                'Test ?? inside',
                static fn() => null ?? 1,
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return static fn() => null ?? 1;
PHP,
            ],
            [
                'Test ?? outside',
                (static fn() => null) ?? 1,
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return static fn() => null;
PHP,
            ],
            [
                'Test ?: inside',
                static fn() => null ?: 1,
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return static fn() => null ?: 1;
PHP,
            ],
            [
                'Test ?: outside',
                (static fn() => null) ?: 1,
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return static fn() => null;
PHP,
            ],
            [
                'Test ternary outside',
                true ? static fn() => 1 : 0,
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return static fn() => 1 ;
PHP,
            ],
            [
                'Test ternary inside & outside',
                true ? static fn() => true ? 1 : -1 : 0,
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return static fn() => true ? 1 : -1 ;
PHP,
            ],
            [
                'Test ternary else branch',
                false ? false : fn() => 1 ? true : false,
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return fn() => 1 ? true : false;
PHP,
            ],
            [
                'Test const in ternary',
function () {
    return true ? Y : (a(Z: true ? 0 : 1) ? false : test(Z: 1));
},
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
use const A1 as Y;
return function () {
    return true ? Y : (a(Z: true ? 0 : 1) ? false : test(Z: 1));
};
PHP,
            ],
            [
                'Test const in ternary 2',
function () {
    return true ? Y : (a_func(Z: true ? Z : 1) ? false : test(Z: 1));
},
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
use const A1 as Y,
          A2 as Z;
return function () {
    return true ? Y : (a_func(Z: true ? Z : 1) ? false : test(Z: 1));
};
PHP,
            ],
            [
                'Test as array item',
                [fn() => [1,],][0],
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return fn() => [1,];
PHP,
            ],
            [
                'Test match',
match(1 === 1) {
    true => fn($x) => match ($x) {
        0 => AttrAlias::class,
        default => Y,
    },
    false => null,
},
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
use MyAttr as AttrAlias;
use const A1 as Y;
return fn($x) => match ($x) {
        0 => AttrAlias::class,
        default => Y,
    };
PHP,
            ],
            [
                'Test oneliner',
                my_one_liner(),
                <<<'PHP'
namespace Opis\Closure\Test\PHP80;
return function () {return "inside oneliner";};
PHP,
            ]
        ];
    }
}

function my_one_liner(){return function () {return "inside oneliner";};}