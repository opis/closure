<?php
/* ===========================================================================
 * Copyright (c) 2018 Zindex Software
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

    /**
     * @expectedException \Opis\Closure\SecurityException
     */
    public function testSecuredClosureWithoutSecuriyProvider()
    {
        SerializableClosure::setSecretKey('secret');

        $closure = function(){
            /*x*/
        };

        $value = serialize(new SerializableClosure($closure));
        SerializableClosure::removeSecurityProvider();
        unserialize($value);
    }
}