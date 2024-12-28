<?php

namespace Opis\Closure;

/**
 * Class used for 3.x unserialize compatibility
 * @deprecated This will be removed in 5.x
 * @internal
 */
final class SelfReference
{
    /**
     * @var string A unique hash representing the object
     */
    public string $hash;

    /**
     * Constructor
     *
     * @param string $hash
     */
    public function __construct(string $hash)
    {
        $this->hash = $hash;
    }
}
