<?php
/* ===========================================================================
 * Opis Project
 * http://opis.io
 * ===========================================================================
 * Copyright (c) 2014-2016 Opis Project
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure;

use RuntimeException;

/**
 * Provides a wrapper for secure serialization of closures
 * @deprecated 2.4.0 Marked for removal in 3.0.0
 */
class SecureClosure extends SerializableClosure
{
    /**
     * @var \Opis\Closure\SecurityProviderInterface Security provider
     */

    protected static $_securityProvider;

    /**
     * Set the security provider
     *
     * @param  SecurityProviderInterface $provider Security provider
     */

    public static function setSecurityProvider(SecurityProviderInterface $provider)
    {
        static::$_securityProvider = $provider;
    }

    /**
     * Get the security provider
     *
     * @return   SecurityProviderInterface
     */

    public static function getSecurityProvider()
    {
        return static::$_securityProvider;
    }

    /**
     * Override unserialize method
     *
     * @param   string $data Serialized data
     * @throws RuntimeException
     * @throws SecurityException
     */
    public function unserialize($data)
    {
        if (static::$_securityProvider === null) {
            throw new RuntimeException('You must set a security provider in order to use this class');
        }

        $data = unserialize($data);

        if (false === $data = &static::$_securityProvider->verify($data)) {
            throw new SecurityException("Your serialized closure might have been modified and it's unsafe to be unserialized." .
                "Make sure you are using the same security provider, with the same settings, " .
                "both for serialization and unserialization.");
        }

        parent::unserialize($data);
    }

    /**
     * Override serialize method
     *
     * @return  string  The serialized closure
     */

    public function serialize()
    {
        if (static::$_securityProvider === null) {
            throw new RuntimeException('You must set a security provider in order to use this class');
        }

        $data = parent::serialize();
        $data = &static::$_securityProvider->sign($data);
        return serialize($data);
    }
}
