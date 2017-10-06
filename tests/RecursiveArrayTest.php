<?php
/* ===========================================================================
 * Copyright 2013-2016 The Opis Project
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

use stdClass;

class RecursiveArrayTest extends \PHPUnit_Framework_TestCase
{
    public function testRecursiveArray()
    {
        $a = ['foo'];
        $a[] = &$a;
        $f = function () use($a){
            return $a[1][0];
        };
        $u = \Opis\Closure\unserialize(\Opis\Closure\serialize($f));
        $this->assertEquals('foo', $u());
    }

    public function testRecursiveArray2()
    {
        $a = ['foo'];
        $a[] = &$a;
        $f = function () use(&$a){
            return $a[1][0];
        };
        $u = \Opis\Closure\unserialize(\Opis\Closure\serialize($f));
        $this->assertEquals('foo', $u());
    }

    public function testRecursiveArray3()
    {
        $f = function () {
            return true;
        };
        $a = [$f];
        $a[] = &$a;

        $u = \Opis\Closure\unserialize(\Opis\Closure\serialize($a));
        $u = $u[1][0];
        $this->assertTrue($u());
    }

    public function testRecursiveArray4()
    {
        $a = [];
        $f = function () use($a) {
            return true;
        };
        $a[] = $f;
        $a[] = &$a;

        $u = \Opis\Closure\unserialize(\Opis\Closure\serialize($a));
        $u = $u[1][0];
        $this->assertTrue($u());
    }

    public function testRecursiveArray5()
    {
        $a = [];
        $f = function () use(&$a) {
            return true;
        };
        $a[] = $f;
        $a[] = &$a;

        $u = \Opis\Closure\unserialize(\Opis\Closure\serialize($a));
        $u = $u[1][0];
        $this->assertTrue($u());
    }

    public function testRecursiveArray6()
    {
        $o = new stdClass();
        $o->a = [];
        $f = function () {
            return true;
        };
        $a = &$o->a;
        $a[] = $f;
        $a[] = &$a;

        $u = \Opis\Closure\unserialize(\Opis\Closure\serialize($o));
        $u = $u->a[1][0];
        $this->assertTrue($u());
    }

    public function testRecursiveArray7()
    {
        $o = new stdClass();
        $o->a = [];
        $f = function () use($o){
            return true;
        };
        $a = &$o->a;
        $a[] = $f;
        $a[] = &$a;

        $u = \Opis\Closure\unserialize(\Opis\Closure\serialize($o));
        $u = $u->a[1][0];
        $this->assertTrue($u());
    }
}