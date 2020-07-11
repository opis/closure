<?php
/* ===========================================================================
 * Copyright (c) 2018-2019 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure\Test;

use Opis\Closure\SecurityException;
use Opis\Closure\SerializableClosure;

class SignedClosureTest extends ClosureTest
{
    public function testSecureClosureIntegrityFail()
    {
        $this->expectException(SecurityException::class);

        $closure = function(){
            /*x*/
        };

        SerializableClosure::setSecretKey('secret');

        $value = serialize(new SerializableClosure($closure));
        $value = str_replace('*x*', '*y*', $value);
        unserialize($value);
    }

    public function testJsonSecureClosureIntegrityFail()
    {
        $this->expectException(SecurityException::class);

        $closure = function(){
            /*x*/
        };

        SerializableClosure::setSecretKey('secret');

        $value = serialize(new JsonSerializableClosure($closure));
        $value = str_replace('*x*', '*y*', $value);
        unserialize($value);
    }

    public function testUnsecuredClosureWithSecurityProvider()
    {
        $this->expectException(SecurityException::class);

        SerializableClosure::removeSecurityProvider();

        $closure = function(){
            /*x*/
        };

        $value = serialize(new SerializableClosure($closure));
        SerializableClosure::setSecretKey('secret');
        unserialize($value);
    }

    public function testJsonUnsecuredClosureWithSecurityProvider()
    {
        $this->expectException(SecurityException::class);

        SerializableClosure::removeSecurityProvider();

        $closure = function(){
            /*x*/
        };

        $value = serialize(new JsonSerializableClosure($closure));
        SerializableClosure::setSecretKey('secret');
        unserialize($value);
    }

    public function testSecuredClosureWithoutSecuriyProvider()
    {
        SerializableClosure::setSecretKey('secret');

        $closure = function(){
            return true;
        };

        $value = serialize(new SerializableClosure($closure));
        SerializableClosure::removeSecurityProvider();
        $closure = unserialize($value)->getClosure();
        $this->assertTrue($closure());
    }

    public function testJsonSecuredClosureWithoutSecuriyProvider()
    {
        SerializableClosure::setSecretKey('secret');

        $closure = function(){
            return true;
        };

        $value = serialize(new SerializableClosure($closure));
        SerializableClosure::removeSecurityProvider();
        $closure = unserialize($value)->getClosure();
        $this->assertTrue($closure());
    }

    public function testInvalidSecuredClosureWithoutSecuriyProvider()
    {
        $this->expectException(SecurityException::class);

        SerializableClosure::setSecretKey('secret');
        $closure = function(){
            /*x*/
        };

        $value = serialize(new SerializableClosure($closure));
        $value = str_replace('.', ',', $value);
        SerializableClosure::removeSecurityProvider();
        unserialize($value);
    }

    public function testInvalidJsonSecuredClosureWithoutSecuriyProvider()
    {
        $this->expectException(SecurityException::class);

        SerializableClosure::setSecretKey('secret');
        $closure = function(){
            /*x*/
        };

        $value = serialize(new JsonSerializableClosure($closure));
        $value = str_replace('hash', 'hash1', $value);
        SerializableClosure::removeSecurityProvider();
        unserialize($value);
    }

    public function testMixedEncodings()
    {
        $a = iconv('utf-8', 'utf-16', "Düsseldorf");
        $b = utf8_decode("Düsseldorf");

        $closure = function() use($a, $b) {
            return [$a, $b];
        };

        SerializableClosure::setSecretKey('secret');

        $value = serialize(new SerializableClosure($closure));
        $u = unserialize($value)->getClosure();
        $r = $u();

        $this->assertEquals($a, $r[0]);
        $this->assertEquals($b, $r[1]);
    }
}