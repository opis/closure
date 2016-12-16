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
 * Interface SecurityProviderInterface
 * @deprecated 2.4.0 Marked for removal in 3.0.0
 */
interface SecurityProviderInterface
{
    /**
     * Sign the serialized closure
     *
     * @param   string &$data Serialized closure
     *
     * @return  mixed
     */

    public function &sign(&$data);

    /**
     * Check data integrity
     *
     * @param   string &$data Signed data
     *
     * @return  string|bool
     */

    public function &verify(&$data);
}
