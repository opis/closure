<?php

namespace Opis\Closure\Test\PHP81;

use Closure;

enum MyEnum : string {
    case CASE1 = "c1";
    public function getClosure(): Closure {
        return fn() => $this;
    }
}
