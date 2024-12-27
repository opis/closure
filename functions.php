<?php

namespace Opis\Closure;

function init(
    Security\SecurityProviderInterface|string|null $security = null,
    bool                                           $v3Compatible = false
): void
{
    Serializer::init($security, $v3Compatible);
}

function serialize(
    mixed                               $data,
    ?Security\SecurityProviderInterface $security = null
): string
{
    return Serializer::serialize($data, $security);
}

function unserialize(
    string                                        $data,
    Security\SecurityProviderInterface|array|null $security = null,
    ?array                                        $options = null
): mixed
{
    if (is_array($security)) {
        // this is only for compatibility with v3
        return Serializer::unserialize($data, null, $security);
    }
    return Serializer::unserialize($data, $security, $options);
}

