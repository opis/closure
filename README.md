Opis Closure
====================
[![Tests](https://github.com/opis/closure/workflows/Tests/badge.svg)](https://github.com/opis/closure/actions)
[![Packagist Version](https://img.shields.io/packagist/v/opis/closure?label=Version)](https://packagist.org/packages/opis/closure)
[![Packagist Downloads](https://img.shields.io/packagist/dt/opis/closure?label=Downloads)](https://packagist.org/packages/opis/closure)
[![Packagist License](https://img.shields.io/packagist/l/opis/closure?color=teal&label=License)](https://packagist.org/packages/opis/closure)

Serialize closures and anonymous classes
------------------

**Opis Closure** is a PHP library that allows you to serialize closures, 
anonymous classes, and arbitrary data.

Key features:

- serialize [closures (anonymous functions)](https://www.php.net/manual/en/functions.anonymous.php)
- serialize [anonymous classes](https://www.php.net/manual/en/language.oop5.anonymous.php)
- does not rely on PHP extensions (no FFI or similar dependencies)
- supports PHP 8.0-8.5 syntax
- handles circular references
- works with [attributes](https://www.php.net/manual/en/language.attributes.overview.php)
- works with [readonly properties](https://www.php.net/manual/en/language.oop5.properties.php#language.oop5.properties.readonly-properties)
- works with [property hooks](https://www.php.net/manual/en/language.oop5.property-hooks.php)
- extensible via [custom serializers and deserializers](https://opis.io/closure/4.x/objects.html)
- supports [cryptographically signed data](https://opis.io/closure/4.x/security.html)
- supports PHP's built-in [SPL and Date classes](https://opis.io/closure/4.x/objects.html#default-object-serializers), and the popular [`nesbot/carbon`](https://github.com/CarbonPHP/carbon) package
- reconstructed code is close to the original and [debugger friendly](https://opis.io/closure/4.x/debug.html)
- and [many more][documentation]

### Example of closure serialization

```php
use function Opis\Closure\{serialize, unserialize};

$serialized = serialize(fn() => "hello from closure!");
$greet = unserialize($serialized);

echo $greet(); // hello from closure!
```

### Example of anonymous class serialization

```php
use function Opis\Closure\{serialize, unserialize};

$serialized = serialize(new class("hello from anonymous class!") {
    public function __construct(private string $message) {}
    
    public function greet(): string {
        return $this->message;
    }
});

$object = unserialize($serialized);
echo $object->greet(); // hello from anonymous class!
```

## Migrating from 3.x

Version 4.x is a full rewrite of the library, but data deserialization from 3.x is possible.
Read the docs on [how to migrate from 3.x][migration].

## Documentation

The full documentation for this library can be found [here][documentation].

## License

**Opis Closure** is licensed under the [MIT License (MIT)][license].

## Requirements

* PHP >= 8.0

## Installation

**Opis Closure** is available on [Packagist], and it can be installed from a 
command line interface by using [Composer]: 

```bash
composer require opis/closure
```

Or you could directly reference it into your `composer.json` file as a dependency

```json
{
    "require": {
        "opis/closure": "^4.4"
    }
}
```

[documentation]: https://opis.io/closure/4.x/ "Opis Closure Documentation"
[migration]: https://opis.io/closure/4.x/migrate.html "Opis Closure Migration guide"
[license]: http://opensource.org/licenses/MIT "MIT License"
[Packagist]: https://packagist.org/packages/opis/closure "Packagist"
[Composer]: https://getcomposer.org "Composer"
[CHANGELOG]: https://github.com/opis/closure/blob/master/CHANGELOG.md "Changelog"
