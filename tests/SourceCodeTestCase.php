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

use Closure;
use PHPUnit\Framework\TestCase;
use Opis\Closure\ReflectionClosure;

abstract class SourceCodeTestCase extends TestCase
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

    abstract public function closureProvider(): array;
}