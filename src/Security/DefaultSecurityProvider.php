<?php

namespace Opis\Closure\Security;

class DefaultSecurityProvider implements SecurityProviderInterface
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * @inheritDoc
     */
    public function sign(string $data): string
    {
        return base64_encode(hash_hmac('sha256', $data, $this->secret, true));
    }

    /**
     * @inheritDoc
     */
    public function verify(string $hash, string $data): bool
    {
        return $hash === $this->sign($data);
    }
}