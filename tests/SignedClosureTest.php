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
    public function testSecureClosureIntegrityFail()
    {
        $this->setExpectedException('Opis\Closure\SecurityException');

        $closure = function(){
            /*x*/
        };

        SerializableClosure::setSecretKey('secret');

        $value = serialize(new SerializableClosure($closure));
        $value = str_replace('*x*', '*y*', $value);
        unserialize($value);
    }

    public function testUnsecuredClosureWithSecurityProvider()
    {
        $this->setExpectedException('Opis\Closure\SecurityException');

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
        $this->setExpectedException('Opis\Closure\SecurityException');

        SerializableClosure::setSecretKey('secret');

        $closure = function(){
            /*x*/
        };

        $value = serialize(new SerializableClosure($closure));
        SerializableClosure::removeSecurityProvider();
        unserialize($value);
    }
}