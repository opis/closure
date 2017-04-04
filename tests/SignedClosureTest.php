<?php
/* ===========================================================================
 * Copyright (c) 2014-2017 The Opis Project
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Colibri\Test;

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