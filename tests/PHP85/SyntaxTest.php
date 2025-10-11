<?php

namespace Opis\Closure\Test\PHP85;

use Opis\Closure\Test\SyntaxTestCase;

class SyntaxTest extends SyntaxTestCase
{
    public function closureProvider(): iterable
    {
        yield [
            'Pipe operator',
static fn(string $input) => $input |> strtoupper(...) |> trim(...),
                <<<'PHP'
namespace Opis\Closure\Test\PHP85;
return static fn(string $input) => $input |> strtoupper(...) |> trim(...);
PHP,
        ];
        yield [
            'Closure as default argument',
static fn(int $number, callable $op = static function (int $value) { return $value * 2; }) => $op($number),
            <<<'PHP'
namespace Opis\Closure\Test\PHP85;
return static fn(int $number, callable $op = static function (int $value) { return $value * 2; }) => $op($number);
PHP,
        ];
        yield [
            'Closure in const expression',
static function(array $callbacks = [
    static function () {
        echo "1";
    },
    static function () {
        echo "2";
    },
]): void {
    foreach ($callbacks as $callback) {
        $callback();
    }
},
            <<<'PHP'
namespace Opis\Closure\Test\PHP85;
return static function(array $callbacks = [
    static function () {
        echo "1";
    },
    static function () {
        echo "2";
    },
]): void {
    foreach ($callbacks as $callback) {
        $callback();
    }
};
PHP,
        ];
        yield [
            'Closure in attribute expression',
static fn() => new class {
    #[XValidator(static function (string $value): bool {
        return strlen($value) <= 32;
    })]
    public string $value = "";
},
            <<<'PHP'
namespace Opis\Closure\Test\PHP85;
return static fn() => new class {
    #[XValidator(static function (string $value): bool {
        return strlen($value) <= 32;
    })]
    public string $value = "";
};
PHP,
        ];
        yield [
            'final property promotion',
static fn() => new class {
    public function __construct(public final int $value = 1)
    {
    }
},
            <<<'PHP'
namespace Opis\Closure\Test\PHP85;
return static fn() => new class {
    public function __construct(public final int $value = 1)
    {
    }
};
PHP,
        ];
    }
}