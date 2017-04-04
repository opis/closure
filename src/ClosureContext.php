<?php
/* ===========================================================================
 * Copyright (c) 2014-2017 The Opis Project
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
     * @var SplObjectStorage Wrapped closures in this context
     */

    public $instances;

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
        $this->instances = new SplObjectStorage();
        $this->locks = 0;
    }
}