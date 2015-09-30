<?php
/* ===========================================================================
 * Opis Project
 * http://opis.io
 * ===========================================================================
 * Copyright (c) 2014-2015 Opis Project
 * 
 * Licensed under the MIT License
 * =========================================================================== */

use Opis\Closure\SecureClosure;
use Opis\Closure\DefaultSecurityProvider;

class SecureClosureTest extends ClosureTest
{
    
    protected function s($closure, $binded = false)
    {
        if($closure instanceof \Closure)
        {
            if(null === SecureClosure::getSecurityProvider())
            {
                SecureClosure::setSecurityProvider(new DefaultSecurityProvider('secret'));
            }
            
            $closure = new SecureClosure($closure, $binded);
        }
        return unserialize(serialize($closure));
    }
    
    public function testSecureClosureFailWithoutProvider()
    {
        if($this->r())
        {
            $this->markTestSkipped('This test requires PHP >=5.4');
            return;
        }
        
        $this->setExpectedException('RuntimeException');
        
        serialize(new SecureClosure(function(){    
        }));
    }
    
    public function testSecureClosureIntegrityFail()
    {
        $this->setExpectedException('Opis\Closure\SecurityException');
        
        SecureClosure::setSecurityProvider(new DefaultSecurityProvider('secret'));
        
        $closure = function(){
            /*x*/
        };
        $value = serialize(new SecureClosure($closure));
        $value = str_replace('/*x*/', '/*y*/', $value);
        $value = unserialize($value);
        
    }
    
}
