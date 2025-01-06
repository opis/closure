<?php

namespace Opis\Closure\Test;

use Opis\Closure\AbstractParser;
use Opis\Closure\ClosureInfo;
use Opis\Closure\Serializer;
use PHPUnit\Framework\TestCase;

abstract class SerializeTestCase extends TestCase
{
    protected function process(mixed $value): mixed
    {
        return Serializer::unserialize(Serializer::serialize($value));
    }

    protected function tearDown(): void
    {
        // clear cache if any
        ClosureInfo::clear();
        AbstractParser::clear();
        // do not keep security provider
        Serializer::setSecurityProvider(null);
    }
}