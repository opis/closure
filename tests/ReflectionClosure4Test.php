<?php
/* ===========================================================================
 * Copyright 2014-2017 The Opis Project
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


class ReflectionClosure4Test extends \PHPUnit\Framework\TestCase
{
    protected function c(Closure $closure)
    {
        $r = new ReflectionClosure($closure);
        return $r->getCode();
    }

    public function testResolveArguments()
    {
        $f1 = function (object $p){};
        $e1 = 'function (object $p){}';

        $this->assertEquals($e1, $this->c($f1));
    }

    public function testResolveReturnType()
    {
        $f1 = function (): object{};
        $e1 = 'function (): object{}';


        $this->assertEquals($e1, $this->c($f1));
    }
}