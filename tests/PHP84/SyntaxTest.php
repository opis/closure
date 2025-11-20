<?php

namespace Opis\Closure\Test\PHP84;

use Opis\Closure\Test\SyntaxTestCase;

class SyntaxTest extends SyntaxTestCase
{
    public function closureProvider(): iterable
    {
        yield [
            'New without parenthesis',
static fn() => new class {},
            <<<'PHP'
namespace Opis\Closure\Test\PHP84;
return static fn() => new class {};
PHP,
        ];
        yield [
            'Asymmetric Property Visibility',
static fn() => new class("Opis") {
    public protected(set) string $name;
    public function __construct(string $name)
    {
        $this->name = $name;
    }
},
                <<<'PHP'
namespace Opis\Closure\Test\PHP84;
return static fn() => new class("Opis") {
    public protected(set) string $name;
    public function __construct(string $name)
    {
        $this->name = $name;
    }
};
PHP,
        ];
        yield [
            'Property Hooks',
static fn() => new class() {
    public string $fullName {
        get => $this->firstName . ' ' . $this->lastName;
    }
    public string $firstName {
        set => ucfirst(strtolower($value));
    }
    protected string $lastName {
        set {
            $this->lastName = $value;
        }
        get {
            return "X" . $this->lastName;
        }
    }
},
            <<<'PHP'
namespace Opis\Closure\Test\PHP84;
return static fn() => new class() {
    public string $fullName {
        get => $this->firstName . ' ' . $this->lastName;
    }
    public string $firstName {
        set => ucfirst(strtolower($value));
    }
    protected string $lastName {
        set {
            $this->lastName = $value;
        }
        get {
            return "X" . $this->lastName;
        }
    }
};
PHP,
        ];
    }
}