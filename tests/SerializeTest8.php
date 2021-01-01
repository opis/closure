<?php
/* ============================================================================
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

// Test only

use MyAttr as AttrAlias;
use SomeClass as ClassAlias;
use Opis\Closure as OpisClosure;

class SerializeTest8 extends TestCase
{
    /**
     * @param string $message
     * @param Closure $closure
     * @param string $code
     * @dataProvider closureProvider
     */
    public function testSourceCode(string $message, Closure $closure, string $code)
    {
        $code = $this->lineEndings("<?php" . PHP_EOL . $code);
        $source = $this->lineEndings((new ReflectionClosure($closure))->getCode());

        $this->assertEquals($code, $source, $message);
    }

    private function lineEndings(string $data): string
    {
        return str_replace("\r\n", "\n", $data);
    }

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
                'Test ns alias',
                static fn(OpisClosure\A $a, OpisClosure\B $b, Other\C $c): int => 0,
                <<<'PHP'
namespace Opis\Closure\Test;
use Opis\Closure as OpisClosure;
return static fn(OpisClosure\A $a, OpisClosure\B $b, Other\C $c): int => 0;
PHP,
            ],
        ];
    }
}