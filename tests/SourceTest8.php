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
use const A1 as Y;
use const A2 as Z;

class SourceTest8 extends SourceCodeTestCase
{
    public function closureProvider(): array
    {
        return [
            [
                'Test const in ternary',
function () {
    return true ? Y : (a(Z: true ? 0 : 1) ? false : test(Z: 1));
},
                <<<'PHP'
namespace Opis\Closure\Test;
use const A1 as Y;
return function () {
    return true ? Y : (a(Z: true ? 0 : 1) ? false : test(Z: 1));
};
PHP,
            ],
            [
                'Test const in ternary 2',
function () {
    return true ? Y : (a(Z: true ? Z : 1) ? false : test(Z: 1));
},
                <<<'PHP'
namespace Opis\Closure\Test;
use const A1 as Y,
          A2 as Z;
return function () {
    return true ? Y : (a(Z: true ? Z : 1) ? false : test(Z: 1));
};
PHP,
            ],
        ];
    }
}