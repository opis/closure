<?php

namespace Opis\Closure\Test\PHP80;

use Opis\Closure\Security\{
    SecurityException,
    DefaultSecurityProvider,
    SecurityProviderInterface
};
use Opis\Closure\Serializer;
use PHPUnit\Framework\TestCase;

class UnserializeTest extends TestCase
{
    public function testSum()
    {
        $f = $this->u("sum");
        $this->assertEquals(5, $f(2, 3));
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

    public function testSumSecurityWrongSecret()
    {
        $this->expectException(SecurityException::class);
        $f = $this->u("sum.security", "other-secret");
    }

    private function u(string $name, SecurityProviderInterface|string|null $security = null): mixed
    {
        $data = file_get_contents(__DIR__ . "/v4/{$name}.bin");
        if (is_string($security)) {
            $security = new DefaultSecurityProvider($security);
        }
        return Serializer::unserialize($data, $security);
    }
}