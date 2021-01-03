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

use Opis\Closure\SerializableClosure;
use PHPUnit\Framework\TestCase;

class Unserialize3Test extends TestCase
{
    /**
     * @dataProvider fileDataProvider
     */
    public function testUnserialize(string $name, string $data, ?string $secret, array $call, $expect)
    {
        if ($secret) {
            SerializableClosure::setSecretKey($secret);
        }

        $closure = unserialize($data);

        $this->assertEquals($expect, $closure(...$call), $name);

        if ($secret) {
            SerializableClosure::removeSecurityProvider();
        }
    }

    public function fileDataProvider(): array
    {
        $data = json_decode(file_get_contents(__DIR__ . '/data.3.x.json'), false);
        return array_map(fn ($v) => (array) $v, $data);
    }
}