<?php

namespace Opis\Closure\Security;

interface SecurityProviderInterface
{
    /**
     * Sign data
     * @param string $data
     * @return string Should not contain new line \n
     */
    public function sign(string $data): string;

    /**
     * @param string $hash
     * @param string $data
     * @return bool
     */
    public function verify(string $hash, string $data): bool;
}