<?php
/* ===========================================================================
 * Copyright (c) 2020 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure\Test;

use Closure;
use Opis\Closure\ReflectionClosure;
use PHPUnit\Framework\TestCase;

abstract class AbstractClosureSourceCodeTestCase extends TestCase
{
    /**
     * @dataProvider sourceCodeDataProvider
     */
    public function testSourceCode(Closure $closure, ?string $code, ?string $message = null)
    {
        $this->assertEquals($code,(new ReflectionClosure($closure))->getCode(), $message ?? "");
    }

    /**
     * @param iterable $items
     * @param string $name
     * @return iterable
     */
    protected function items(iterable $items, string $name): iterable
    {
        foreach ($items as $key => $item) {
            $text = " => {$name}({$key})";
            if (count($item) === 2) {
                $item[] = $text;
            } else {
                $item[2] .= $text;
            }

            yield $item;
        }
    }

    /**
     * @return iterable
     */
    abstract public function sourceCodeDataProvider(): iterable;
}