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


/**
 * Helper class used to indicate a reference to an object
 */
class SelfReference
{
    /**
     * @var string An unique hash representing the object
     */
    public $hash;

    /**
     * Constructor
     *
     * @param object $object
     */
    public function __construct($object)
    {
        $this->hash = spl_object_hash($object);
    }
}