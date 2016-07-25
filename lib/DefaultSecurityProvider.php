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

class DefaultSecurityProvider implements SecurityProviderInterface
{
    /**
     * @var string  Secret key
     */

    protected $key;

    /**
     * Constructor
     *
     * @param   string $key Secret key
     */

    public function __construct($key)
    {
        $this->key = $key;
    }

    /**
     * Implementation of \Opis\Closure\SecurityProvider::sign
     *
     * @param   string &$data Serialized closure
     *
     * @return  array   Secured data
     */

    public function &sign(&$data)
    {
        $container = array(
            'data' => $data,
            'hash' => base64_encode(hash_hmac('sha256', $data, $this->key, true)),
        );

        return $container;
    }

    /**
     * Implementation of \Opis\Closure\SecurityProvider::verify
     *
     * @param   array &$data Secured data
     *
     * @return  string|bool     Returns `false` if the signature is not valid
     */

    public function &verify(&$data)
    {
        $result = false;
        $check = base64_encode(hash_hmac('sha256', $data['data'], $this->key, true));

        if ($check === $data['hash']) {
            $result = &$data['data'];
        }

        return $result;
    }
}
