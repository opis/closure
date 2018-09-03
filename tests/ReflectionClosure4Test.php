<?php
/* ===========================================================================
 * Copyright (c) 2018 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

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