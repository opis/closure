<?php

namespace Opis\Closure\Test;

use Opis\Closure\Serializer;
use PHPUnit\Framework\TestCase;

abstract class SerializeTestCase extends TestCase
{
    protected function process(mixed $value): mixed
    {
        return Serializer::unserialize(Serializer::serialize($value));
    }
}