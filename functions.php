<?php
/* ===========================================================================
 * Copyright (c) 2018-2019 Zindex Software
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
    SerializableClosure::enterContext();
    SerializableClosure::wrapClosures($data);
    $data = \serialize($data);
    SerializableClosure::exitContext();
    return $data;
}

/**
 * Unserialize
 *
 * @param $data
 * @param $options
 * @return mixed
 */
function unserialize($data, array $options = null)
{
    SerializableClosure::enterContext();
    if ($options === null || PHP_MAJOR_VERSION < 7) {
        $data = \unserialize($data);
    } else {
        $data = \unserialize($data, $options);
    }
    SerializableClosure::unwrapClosures($data);
    SerializableClosure::exitContext();
    return $data;
}
