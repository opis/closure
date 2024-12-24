<?php

namespace Opis\Closure\Test\PHP80;

use Opis\Closure\Serializer;
use Opis\Closure\Security\{DefaultSecurityProvider, SecurityException, SecurityProviderInterface};
use PHPUnit\Framework\TestCase;

class V3CompatTest extends TestCase
{
    public function testSum()
    {
        $f = $this->u("sum");
        $this->assertEquals(5, $f(2, 3));
    }

    public function testSelfRef()
    {
        $f = $this->u("self-ref");
        $this->assertFalse($f($this));
        $this->assertTrue($f($f));
    }

    public function testComplexRef()
    {
        $f = $this->u("complex-ref");
        $arr = $f("key", "value");
        $this->assertEquals($f, $arr["self"]);
        $this->assertEquals(1, $arr["a"]);
        $this->assertEquals(1, $arr["ref_to_a"]);
        $f("a", 2);
        $this->assertEquals(2, $arr["a"]);
        $this->assertEquals(2, $arr["ref_to_a"]);
    }

    public function testSumSecurity()
    {
        $f = $this->u("sum.security", "opis-secret");
        $this->assertEquals(15, $f(10, 5));
    }

    public function testSumSecurityNoSecret()
    {
        $this->expectException(SecurityException::class);
        $f = $this->u("sum.security");
    }

    private function u(string $name, SecurityProviderInterface|string|null $security = null): mixed
    {
        $data = file_get_contents(__DIR__ . "/v3/{$name}.bin");
        if (is_string($security)) {
            $security = new DefaultSecurityProvider($security);
        }
        return Serializer::unserialize($data, $security);
    }
}