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
    protected static $provider;

    protected function s($closure, $bindThis = false)
    {
        SerializableClosure::setSecretKey('secret');
        return parent::s($closure, $bindThis);
    }

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
}