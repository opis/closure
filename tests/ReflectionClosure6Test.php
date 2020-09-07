<?php declare(strict_types=1);

namespace Opis\Closure\Test;

use Closure;
use Opis\Closure\ReflectionClosure;

// Fake
use Opis\Closure\SerializableClosure;
use Some\ClassName as ClassAlias;

final class ReflectionClosure6Test extends \PHPUnit\Framework\TestCase
{
    protected function c(Closure $closure)
    {
        $r = new ReflectionClosure($closure);

        return $r->getCode();
    }

    protected function s($closure)
    {
        $closure = new SerializableClosure($closure);

        return unserialize(serialize($closure))->getClosure();
    }

    public function testUnionTypes()
    {
        $f1 = fn(): string|int|false|Bar|null => 1;
        $e1 = 'fn(): string|int|false|\Opis\Closure\Test\Bar|null => 1';

        $f2 = fn(): \Foo|\Bar => 1;
        $e2 = 'fn(): \Foo|\Bar => 1';

        $f3 = fn(): int|false => false;
        $e3 = 'fn(): int|false => false';

        $f4 = function (): null|MyClass|ClassAlias|Relative\Ns\ClassName|\Absolute\Ns\ClassName { return null; };
        $e4 = 'function (): null|\Opis\Closure\Test\MyClass|\Some\ClassName|\Opis\Closure\Test\Relative\Ns\ClassName|\Absolute\Ns\ClassName { return null; }';

        $this->assertEquals($e1, $this->c($f1));
        $this->assertEquals($e2, $this->c($f2));
        $this->assertEquals($e3, $this->c($f3));
        $this->assertEquals($e4, $this->c($f4));

        self::assertTrue(true);
    }

    public function testMixedType()
    {
        $f1 = function (): mixed { return 42; };
        $e1 = 'function (): mixed { return 42; }';

        $this->assertEquals($e1, $this->c($f1));
    }

    public function testNullsafeOperator()
    {
        $f1 = function () { $obj = new \stdClass(); return $obj?->invalid(); };
        $e1 = 'function () { $obj = new \stdClass(); return $obj?->invalid(); }';

        $this->assertEquals($e1, $this->c($f1));
    }

    public function testTraillingComma()
    {
        $f1 = function (string $param,) {};
        $e1 = 'function (string $param,) {}';

        $this->assertEquals($e1, $this->c($f1));
    }

    public function testNamedParameter()
    {
        $f1 = function(string $firstName, string $lastName) { return $firstName . ' ' . $lastName;};

        $unserialized = $this->s($f1);

        $this->assertEquals('Marco Deleu', $unserialized(
            lastName: 'Deleu',
            firstName: 'Marco'
        ));
    }

    public function testConstructorPropertyPromotion()
    {
        $class = new PropertyPromotion('public', 'protected', 'private');

        $f1 = fn() => $class;

        $object = $this->s($f1)();

        $this->assertEquals('public', $object->public);
        $this->assertEquals('protected', $object->getProtected());
        $this->assertEquals('private', $object->getPrivate());
    }
}

class PropertyPromotion
{
    public function __construct(
        public string $public,
        protected string $protected,
        private string $private,
    ) {}

    public function getProtected(): string
    {
        return $this->protected;
    }

    public function getPrivate(): string
    {
        return $this->private;
    }
}
