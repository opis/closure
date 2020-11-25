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

class SerializeTest8 extends TestCase
{
    /**
     * @param Closure $closure
     * @param string $code
     * @dataProvider closureProvider
     */
    public function testAttributes(Closure $closure, string $code)
    {
        $this->assertEquals("<?php\n" . $code, (new ReflectionClosure($closure))->getCode());
    }

    public function closureProvider(): array
    {
        return [
            [
                #[MyAttr] /*comment*/ #[AttrAlias(1, ClassAlias::class, namespace\Other::class)] static fn(#[MyAttr('param')] int $i) => 1,
                <<<'PHP'
namespace Opis\Closure\Test;
use MyAttr as AttrAlias,
    SomeClass as ClassAlias;
return #[MyAttr] /*comment*/ #[AttrAlias(1, ClassAlias::class, namespace\Other::class)] static fn(#[MyAttr('param')] int $i) => 1;
PHP,
            ],
        ];
    }
}