<?php
/* ============================================================================
 * Copyright 2020-2021 Zindex Software
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

// Test only

use MyAttr as AttrAlias;
use SomeClass as ClassAlias;

class SerializeTest8 extends SourceCodeTestCase
{
    public function closureProvider(): array
    {
        return [
            [
                'Test union types',
                static fn(int | string $a, ClassAlias | AttrAlias | null $b): int | false | null => false,
                <<<'PHP'
namespace Opis\Closure\Test;
use MyAttr as AttrAlias,
    SomeClass as ClassAlias;
return static fn(int | string $a, ClassAlias | AttrAlias | null $b): int | false | null => false;
PHP,
            ],
            [
                'Test PHP attributes',
                #[MyAttr] /*comment*/ #[AttrAlias(1, ClassAlias::class, namespace\Other::class)] static fn(#[MyAttr('param')] int $i) => 1,
                <<<'PHP'
namespace Opis\Closure\Test;
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
namespace Opis\Closure\Test;
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
namespace Opis\Closure\Test;
use SomeClass as ClassAlias;
return static function() { return named_func(AttrAlias: null, named2: ClassAlias::class); };
PHP,
            ],
            [
                'Named args long function 2',
static function() { return named_func(AttrAlias: new AttrAlias(), named2: ClassAlias::class); },
                <<<'PHP'
namespace Opis\Closure\Test;
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
namespace Opis\Closure\Test;
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
namespace Opis\Closure\Test;
return static fn() => named_func(name1: 1, name2: [1, 2, 3]);
PHP,
            ],
            [
                'Named args conflicting with alias',
                static fn() => named_func(AttrAlias: 1, name2: [1, 2, 3]),
                <<<'PHP'
namespace Opis\Closure\Test;
return static fn() => named_func(AttrAlias: 1, name2: [1, 2, 3]);
PHP,
            ],
            [
                'Named args conflicting with alias 2',
                static fn() => named_func(AttrAlias: new AttrAlias(), name2: [1, 2, 3]),
                <<<'PHP'
namespace Opis\Closure\Test;
use MyAttr as AttrAlias;
return static fn() => named_func(AttrAlias: new AttrAlias(), name2: [1, 2, 3]);
PHP,
            ],
            [
                'Named args inside ternary',
                true ? static fn() => named_func(AttrAlias: 1, name2: [1, 2, 3]) : null,
                <<<'PHP'
namespace Opis\Closure\Test;
return static fn() => named_func(AttrAlias: 1, name2: [1, 2, 3]) ;
PHP,
            ],
            [
                'Named args complex ternary',
                true ? static fn() => true ? named_func(AttrAlias: 1, name2: [1, 2, 3]) : false : null,
                <<<'PHP'
namespace Opis\Closure\Test;
return static fn() => true ? named_func(AttrAlias: 1, name2: [1, 2, 3]) : false ;
PHP,
            ],
            [
                'Named args complex ternary 2',
                true ? static fn() => (true ? named_func(AttrAlias: 1, name2: [1, 2, 3]) : false) : null,
                <<<'PHP'
namespace Opis\Closure\Test;
return static fn() => (true ? named_func(AttrAlias: 1, name2: [1, 2, 3]) : false) ;
PHP,
            ],
            [
                'Named args complex ternary 3',
                true ? static fn() => true ? named_func(AttrAlias: named(x: 1, y: 2), name2: (named(a: 1) ? named(b: 1) : named(c: 2)) ? 1 : 0) : false : null,
                <<<'PHP'
namespace Opis\Closure\Test;
return static fn() => true ? named_func(AttrAlias: named(x: 1, y: 2), name2: (named(a: 1) ? named(b: 1) : named(c: 2)) ? 1 : 0) : false ;
PHP,
            ],
        ];
    }
}