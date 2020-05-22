<?php
/* ===========================================================================
 * Copyright (c) 2020 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure\Test;

// Fake
use Foo\Bar;
use Foo\{Baz as Qux};
// Dirty CS
define(Bar::class, Bar::class);

use function Foo\f1;
use function Bar\{b1, b2 as b3};

// ---

class ClosureSourceCodeTest extends AbstractClosureSourceCodeTestCase
{
    /**
     * @inheritDoc
     */
    public function sourceCodeDataProvider(): iterable
    {
        yield from $this->resolveArguments();
        yield from $this->resolveReturnType();
        yield from $this->resolveClassesInBody();
        yield from $this->resolveStaticMethod();
        yield from $this->resolveClassRelatedKeywords();
        yield from $this->resolveClosureInsideClosure();
        yield from $this->resolveAnonymousClassInsideClosure();
        yield from $this->resolveKeywordAsStaticMethod();
        yield from $this->resolveTraitNamesInAnonymousClasses();
        yield from $this->resolveAliasedFunctions();
        yield from $this->resolveBasicShortClosures();
    }

    protected function resolveArguments(): iterable
    {
        yield from $this->items([
            [
                function (Bar $p) {},
                'function (\Foo\Bar $p) {}',
            ],
            [
                function (Bar\Test $p) {},
                'function (\Foo\Bar\Test $p) {}',
            ],
            [
                function (Qux $p) {},
                'function (\Foo\Baz $p) {}',
            ],
            [
                function (Qux\Test $p) {},
                'function (\Foo\Baz\Test $p) {}',
            ],
            [
                function (\Foo $p) {},
                'function (\Foo $p) {}',
            ],
            [
                function (Foo $p) {},
                'function (\\' . __NAMESPACE__ . '\Foo $p) {}',
            ],
            [
                function (array $p, string $x, iterable $i, callable $f){},
                'function (array $p, string $x, iterable $i, callable $f){}',
            ],
            [
                function (?Bar $p){},
                'function (?\Foo\Bar $p){}',
            ],
            [
                function (?Bar\Test $p){},
                'function (?\Foo\Bar\Test $p){}',
            ],
            [
                function (?Qux $p){},
                'function (?\Foo\Baz $p){}',
            ],
            [
                function (?Qux\Test $p){},
                'function (?\Foo\Baz\Test $p){}',
            ],
            [
                function (?array $p, ?string $x){},
                'function (?array $p, ?string $x){}',
            ],
            [
                function (?object $obj, ?string ...$rest){},
                'function (?object $obj, ?string ...$rest){}',
            ],
        ], __FUNCTION__);
    }

    protected function resolveReturnType(): iterable
    {
        yield from $this->items([
            [
                function (): Bar{},
                'function (): \Foo\Bar{}',
            ],
            [
                function (): Bar\Test{},
                'function (): \Foo\Bar\Test{}',
            ],
            [
                function (): Qux{},
                'function (): \Foo\Baz{}',
            ],
            [
                function (): Qux\Test{},
                'function (): \Foo\Baz\Test{}',
            ],
            [
                function (): \Foo{},
                'function (): \Foo{}',
            ],
            [
                function (): Foo{},
                'function (): \\' . __NAMESPACE__. '\Foo{}',
            ],
            [
                function (): array{},
                'function (): array{}',
            ],
            [
                function (): callable{},
                'function (): callable{}',
            ],
            [
                function (): iterable{},
                'function (): iterable{}',
            ],
            [
                function (): string{},
                'function (): string{}',
            ],
            [
                function (): ?Bar{},
                'function (): ?\Foo\Bar{}',
            ],
            [
                function (): ?Bar\Test{},
                'function (): ?\Foo\Bar\Test{}',
            ],
            [
                function (): ?Qux{},
                'function (): ?\Foo\Baz{}',
            ],
            [
                function (): ?Qux\Test{},
                'function (): ?\Foo\Baz\Test{}',
            ],
            [
                function (): ?\Foo{},
                'function (): ?\Foo{}',
            ],
            [
                function (): ?Foo{},
                'function (): ?\\' . __NAMESPACE__. '\Foo{}',
            ],
            [
                function (): self{},
                'function (): self{}',
            ],
            [
                function (): ?array{},
                'function (): ?array{}',
            ],
            [
                function (): ?string{},
                'function (): ?string{}',
            ],
            [
                function (): void{},
                'function (): void{}',
            ],
            [
                function (): object{},
                'function (): object{}',
            ],
        ], __FUNCTION__);
    }

    protected function resolveClassesInBody(): iterable
    {
        yield from $this->items([
            [
                function () { return new Bar(); },
                'function () { return new \Foo\Bar(); }',
            ],
            [
                function () { return new Bar\Test(); },
                'function () { return new \Foo\Bar\Test(); }',
            ],
            [
                function () { return new Qux(); },
                'function () { return new \Foo\Baz(); }',
            ],
            [
                function () { return new Qux\Test(); },
                'function () { return new \Foo\Baz\Test(); }',
            ],
            [
                function () { return new \Foo(); },
                'function () { return new \Foo(); }',
            ],
            [
                function () { return new Foo(); },
                'function () { return new \\' . __NAMESPACE__ . '\Foo(); }',
            ],
        ], __FUNCTION__);
    }

    protected function resolveStaticMethod(): iterable
    {
        yield from $this->items([
            [
                function () { return Bar::test(); },
                'function () { return \Foo\Bar::test(); }',
            ],
            [
                function () { return Bar\Test::test(); },
                'function () { return \Foo\Bar\Test::test(); }',
            ],
            [
                function () { return Qux::test(); },
                'function () { return \Foo\Baz::test(); }',
            ],
            [
                function () { return Qux\Test::test(); },
                'function () { return \Foo\Baz\Test::test(); }',
            ],
            [
                function () { return \Foo::test(); },
                'function () { return \Foo::test(); }',
            ],
            [
                function () { return Foo::test(); },
                'function () { return \\' . __NAMESPACE__ . '\Foo::test(); }',
            ],
        ], __FUNCTION__);
    }

    protected function resolveClassRelatedKeywords(): iterable
    {
        yield from $this->items([
            [
                function (){ $c = '\A'; new $c;},
                'function (){ $c = \'\A\'; new $c;}',
            ],
            [
                function (){ $c = null; $b = '\X\y'; v($c instanceof $b);},
                'function (){ $c = null; $b = \'\X\y\'; v($c instanceof $b);}',
            ],
            [
                function() { return static::foo(); },
                'function() { return static::foo(); }',
            ],
            [
                function ($a) { return $a instanceof static; },
                'function ($a) { return $a instanceof static; }',
            ],
            [
                function() { return self::foo(); },
                'function() { return self::foo(); }',
            ],
            [
                function ($a) { return $a instanceof self; },
                'function ($a) { return $a instanceof self; }',
            ],
            [
                function() { return parent::foo(); },
                'function() { return parent::foo(); }',
            ],
            [
                function ($a) { return $a instanceof parent; },
                'function ($a) { return $a instanceof parent; }',
            ],
            [
                function () { return static::class . self::class . parent::class; },
                'function () { return static::class . self::class . parent::class; }',
            ],
        ], __FUNCTION__);
    }

    protected function resolveClosureInsideClosure(): iterable
    {
        yield from $this->items([
            [
                function() { return function ($a): A { return $a; }; },
                'function() { return function ($a): \Opis\Closure\Test\A { return $a; }; }',
            ],
            [
                function() { return function (A $a): A { return $a; }; },
                'function() { return function (\Opis\Closure\Test\A $a): \Opis\Closure\Test\A { return $a; }; }',
            ],
        ], __FUNCTION__);
    }

    protected function resolveAnonymousClassInsideClosure(): iterable
    {
        yield from $this->items([
            [
                function() { return new class extends A {}; },
                'function() { return new class extends \Opis\Closure\Test\A {}; }',
            ],
            [
                function() { return new class extends A implements B {}; },
                'function() { return new class extends \Opis\Closure\Test\A implements \Opis\Closure\Test\B {}; }',
            ],
            [
                function() { return new class { function x(A $a): B {} }; },
                'function() { return new class { function x(\Opis\Closure\Test\A $a): \Opis\Closure\Test\B {} }; }',
            ],
        ], __FUNCTION__);
    }

    protected function resolveKeywordAsStaticMethod(): iterable
    {
        yield from $this->items([
            [
                function() { Bar::new(); },
                'function() { \Foo\Bar::new(); }',
            ],
            [
                function() { Bar::__FILE__(); },
                'function() { \Foo\Bar::__FILE__(); }',
            ],
            [
                function() { Bar::__CLASS__(); },
                'function() { \Foo\Bar::__CLASS__(); }',
            ],
            [
                function() { Bar::__DIR__(); },
                'function() { \Foo\Bar::__DIR__(); }',
            ],
            [
                function() { Bar::__FUNCTION__(); },
                'function() { \Foo\Bar::__FUNCTION__(); }',
            ],
            [
                function() { Bar::__METHOD__(); },
                'function() { \Foo\Bar::__METHOD__(); }',
            ],
            [
                function() { Bar::function(); },
                'function() { \Foo\Bar::function(); }',
            ],
            [
                function() { Bar::instanceof(); },
                'function() { \Foo\Bar::instanceof(); }',
            ],
            [
                function() { Bar::__LINE__(); },
                'function() { \Foo\Bar::__LINE__(); }',
            ],
            [
                function() { Bar::__NAMESPACE__(); },
                'function() { \Foo\Bar::__NAMESPACE__(); }',
            ],
            [
                function() { Bar::__TRAIT__(); },
                'function() { \Foo\Bar::__TRAIT__(); }',
            ],
            [
                function() { Bar::use(); },
                'function() { \Foo\Bar::use(); }',

            ],
        ], __FUNCTION__);
    }

    protected function resolveTraitNamesInAnonymousClasses(): iterable
    {
        yield from $this->items([
            [
                function () { new class { use Bar; }; },
                'function () { new class { use \Foo\Bar; }; }',
            ],
            [
                function () { new class { use Bar\Test; }; },
                'function () { new class { use \Foo\Bar\Test; }; }',
            ],
            [
                function () { new class { use Qux; }; },
                'function () { new class { use \Foo\Baz; }; }',
            ],
            [
                function () { new class { use Qux\Test; }; },
                'function () { new class { use \Foo\Baz\Test; }; }',
            ],
            [
                function () { new class { use \Foo; }; },
                'function () { new class { use \Foo; }; }',
            ],
            [
                function () { new class { use Foo; }; },
                'function () { new class { use \\' . __NAMESPACE__ . '\Foo; }; }',
            ],
            [
                function () { new class { use Bar; }; function a(Qux $q): Bar { f1(); $a = new class extends Bar {}; } },
                'function () { new class { use \Foo\Bar; }; function a(\Foo\Baz $q): \Foo\Bar '
                . '{ \Foo\f1(); $a = new class extends \Foo\Bar {}; } }',
            ],
        ], __FUNCTION__);
    }

    protected function resolveAliasedFunctions(): iterable
    {
        yield from $this->items([
            [
                function () { return b3(b1());},
                'function () { return \Bar\b2(\Bar\b1());}',
            ],
        ], __FUNCTION__);
    }

    protected function resolveBasicShortClosures(): iterable
    {
        return $this->items([
            [
                $f1 = fn() => "hello",
                'fn() => "hello"',
            ],
            [
                fn&() => "hello",
                'fn&() => "hello"',
            ],
            [
                fn($a, &$b, int ...$rest): string => "hello",
                'fn($a, &$b, int ...$rest): string => "hello"',
            ],
            [
                fn ($a) => ($a === true) && (!empty([0,1,])),
                'fn ($a) => ($a === true) && (!empty([0,1,]))',
            ],
            [
                fn(Bar $a) : Qux => new self(),
                'fn(\Foo\Bar $a) : \Foo\Baz => new self()',
            ],
            [
                fn(Bar $a) : Qux => new static(),
                'fn(\Foo\Bar $a) : \Foo\Baz => new static()',
            ],
            [
                fn(Bar $a) : Qux => new parent(),
                'fn(\Foo\Bar $a) : \Foo\Baz => new parent()',
            ],
        ], __FUNCTION__);
    }
}