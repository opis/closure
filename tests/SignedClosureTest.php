<?php
/* ===========================================================================
 * Copyright (c) 2018-2019 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure\Test;

use Opis\Closure\SerializableClosure;

class SignedClosureTest extends ClosureTest
{
    /**
     * @expectedException \Opis\Closure\SecurityException
     */
    public function testSecureClosureIntegrityFail()
    {
        $closure = function(){
            /*x*/
        };

        SerializableClosure::setSecretKey('secret');

        $value = serialize(new SerializableClosure($closure));
        $value = str_replace('*x*', '*y*', $value);
        unserialize($value);
    }

    public function testSecureInvalidUtf8Data()
    {
        $a = utf8_decode("Düsseldorf");
        $closure = function() use($a)
        {
            return $a;
        };

        SerializableClosure::setSecretKey('secret');

        $value = serialize(new SerializableClosure($closure));
        $u = unserialize($value);

        $c = $u->getClosure();
        $this->assertEquals($a, $c());
    }

    /**
     * @expectedException \Opis\Closure\SecurityException
     */
    public function testUnsecuredClosureWithSecurityProvider()
    {
        SerializableClosure::removeSecurityProvider();

        $closure = function(){
            /*x*/
        };

        $value = serialize(new SerializableClosure($closure));
        SerializableClosure::setSecretKey('secret');
        unserialize($value);
    }

    public function testSecuredClosureWithoutSecuriyProvider()
    {
        SerializableClosure::setSecretKey('secret');

        $closure = function(){
            /*x*/
        };

        $value = serialize(new SerializableClosure($closure));
        SerializableClosure::removeSecurityProvider();
        $value = unserialize($value);
        $this->assertInstanceOf('Opis\\Closure\\SerializableClosure', $value);
    }

    /**
     * @expectedException \Opis\Closure\SecurityException
     */
    public function testInvalidSecuredClosureWithoutSecuriyProvider()
    {
        SerializableClosure::setSecretKey('secret');

        $closure = function(){
            /*x*/
        };

        $value = serialize(new SerializableClosure($closure));
        $value = str_replace('hash', 'hash1', $value);
        SerializableClosure::removeSecurityProvider();
        unserialize($value);
    }
}