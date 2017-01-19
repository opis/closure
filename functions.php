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
 * Serialize
 *
 * @param $data
 * @return string
 */
function serialize($data)
{
    SerializableClosure::wrapClosures($data);
    return \serialize($data);
}

/**
 * Unserialize
 *
 * @param $data
 * @return mixed
 */
function unserialize($data)
{
    $data = \unserialize($data);
    SerializableClosure::unwrapClosures($data);
    return $data;
}
