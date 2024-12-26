<?php

namespace Opis\Closure;

function init(Security\SecurityProviderInterface|string|null $security = null, bool $v3Compatible = false): void
{
    Serializer::init($security, $v3Compatible);
}

function serialize(mixed $data, ?Security\SecurityProviderInterface $security = null): string
{
    return Serializer::serialize($data, $security);
}

function unserialize(string $data, ?Security\SecurityProviderInterface $security = null): mixed
{
    return Serializer::unserialize($data, $security);
}

