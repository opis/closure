<?php

namespace Opis\Closure\Test\PHP80\Objects;

trait StaticTrait1
{
    public static function create() {
        return static function () {
            return self::test();
        };
    }

    public static function test() {
        return "ok-trait";
    }
}