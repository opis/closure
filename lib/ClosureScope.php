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
 * Closure scope class
 */
class ClosureScope
{
    /**
     * @var integer Number of serializations in current scope
     */
    public $serializations = 0;

    /**
     * @var integer Number of closures that have to be serialized
     */
    public $toserialize = 0;

    /**
     * @var SplObjectStorage Wrapped closures in current scope
     */
    public $storage;
}