<?php

namespace Opis\Closure\Test;

use Opis\Closure\AbstractInfo;
use Opis\Closure\AbstractParser;
use Opis\Closure\Serializer;
use PHPUnit\Framework\TestCase;

abstract class SerializeTestCase extends TestCase
{
    protected function process(mixed $value): mixed
    {
        return Serializer::unserialize(Serializer::serialize($value));
    }

    protected function s(mixed $value): string
    {
        return Serializer::serialize($value);
    }

    protected function u(string $value): mixed
    {
        return Serializer::unserialize($value);
    }

    protected function tearDown(): void
    {
        // clear cache if any
        $this->clearCache();
        // do not keep security provider
        Serializer::setSecurityProvider(null);
    }

    protected function clearCache(): void
    {
        AbstractInfo::clear();
        AbstractParser::clear();
    }
}