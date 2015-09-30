<?php
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
    
}
