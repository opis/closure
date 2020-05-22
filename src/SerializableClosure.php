<?php
/* ===========================================================================
 * Copyright (c) 2018-2020 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure;

class SerializableClosure
{
    /**
     * @return array
     */
    final public function __serialize(): array
    {
        return SerializableClosureHandler::instance()->serializeClosure($this);
    }

    /**
     * @param array $data
     */
    final public function __unserialize(array $data): void
    {
        SerializableClosureHandler::instance()->unserializeClosure($this, $data);
    }
}