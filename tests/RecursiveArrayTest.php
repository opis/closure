<?php
/* ===========================================================================
 * Copyright (c) 2018-2021 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure\Test;

use stdClass;

class RecursiveArrayTest extends \PHPUnit\Framework\TestCase
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