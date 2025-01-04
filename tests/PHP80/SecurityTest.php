<?php

namespace Opis\Closure\Test\PHP80;

use Opis\Closure\Serializer;
use Opis\Closure\Security\SecurityException;

class SecurityTest extends SerializeTest
{
    public function testHowItShouldWork()
    {
        Serializer::setSecurityProvider("secret");

        $value = \uniqid();
        $data = $this->process($value);

        $this->assertEquals($value, $data);
    }

    public function testUnserializeUnsigned()
    {
        Serializer::setSecurityProvider(null);

        $value = \uniqid();

        $str = Serializer::serialize($value);

        Serializer::setSecurityProvider("secret");

        $this->expectException(SecurityException::class);

        Serializer::unserialize($str);
    }

    public function testUnserializeSignedWithoutSecurityProvider()
    {
        Serializer::setSecurityProvider("secret");

        $value = \uniqid();
        $str = Serializer::serialize($value);

        Serializer::setSecurityProvider(null);

        $this->expectException(SecurityException::class);

        Serializer::unserialize($str);
    }

    public function testInvalidSecurityProvider()
    {
        Serializer::setSecurityProvider("secret_1");

        $value = \uniqid();
        $str = Serializer::serialize($value);

        Serializer::setSecurityProvider("secret_2");

        $this->expectException(SecurityException::class);

        Serializer::unserialize($str);
    }
}