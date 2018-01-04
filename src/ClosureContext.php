<?php
/* ===========================================================================
 * Copyright (c) 2014-2018 The Opis Project
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure;

use SplObjectStorage;

/**
 * Closure context class
 */
class ClosureContext
{
    /**
     * @var ClosureScope Closures scope
     */
    public $scope;

    /**
     * @var integer
     */
    public $locks;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->scope = new ClosureScope();
        $this->locks = 0;
    }
}