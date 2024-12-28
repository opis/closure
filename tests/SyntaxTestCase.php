<?php

namespace Opis\Closure\Test;

use Closure;
use PHPUnit\Framework\TestCase;
use Opis\Closure\ReflectionClosure;

abstract class SyntaxTestCase extends TestCase
{
    /**
     * @param string $message
     * @param Closure $closure
     * @param string $code
     * @dataProvider closureProvider
     */
    public function testSourceCode(string $message, Closure $closure, string $code)
    {
        $code = $this->lineEndings($code);
        $source = $this->lineEndings((new ReflectionClosure($closure))->info()->getIncludePHP(false));

        $this->assertEquals($code, $source, $message);
    }

    private function lineEndings(string $data): string
    {
        return str_replace("\r\n", "\n", $data);
    }

    abstract public function closureProvider(): iterable;
}