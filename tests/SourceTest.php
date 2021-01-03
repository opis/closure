<?php
/* ============================================================================
 * Copyright 2021 Zindex Software
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

use Opis\Closure as OpisClosure;

class SourceTest extends SourceCodeTestCase
{
    public function closureProvider(): array
    {
        return [
            [
                'Test ns alias',
                static fn(OpisClosure\A $a, OpisClosure\B $b, Other\C $c): int => 0,
                <<<'PHP'
namespace Opis\Closure\Test;
use Opis\Closure as OpisClosure;
return static fn(OpisClosure\A $a, OpisClosure\B $b, Other\C $c): int => 0;
PHP,
            ],
            [
                'Test ?? inside',
                static fn() => null ?? 1,
                <<<'PHP'
namespace Opis\Closure\Test;
return static fn() => null ?? 1;
PHP,
            ],
            [
                'Test ?? outside',
                (static fn() => null) ?? 1,
                <<<'PHP'
namespace Opis\Closure\Test;
return static fn() => null;
PHP,
            ],
            [
                'Test ?: inside',
                static fn() => null ?: 1,
                <<<'PHP'
namespace Opis\Closure\Test;
return static fn() => null ?: 1;
PHP,
            ],
            [
                'Test ?: outside',
                (static fn() => null) ?: 1,
                <<<'PHP'
namespace Opis\Closure\Test;
return static fn() => null;
PHP,
            ],
            [
                'Test ternary outside',
                true ? static fn() => 1 : 0,
                <<<'PHP'
namespace Opis\Closure\Test;
return static fn() => 1 ;
PHP,
            ],
            [
                'Test ternary inside & outside',
                true ? static fn() => true ? 1 : -1 : 0,
                <<<'PHP'
namespace Opis\Closure\Test;
return static fn() => true ? 1 : -1 ;
PHP,
            ],
            [
                'Test ternary else branch',
                false ? false : fn() => 1 ? true : false,
                <<<'PHP'
namespace Opis\Closure\Test;
return fn() => 1 ? true : false;
PHP,
            ],
            [
                'Test as array item',
                [fn() => [1,],][0],
                <<<'PHP'
namespace Opis\Closure\Test;
return fn() => [1,];
PHP,
            ],
        ];
    }
}