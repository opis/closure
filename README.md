Opis Closure
====================
[![Tests](https://github.com/opis/closure/workflows/Tests/badge.svg)](https://github.com/opis/closure/actions)
[![Packagist Version](https://img.shields.io/packagist/v/opis/closure?label=Version)](https://packagist.org/packages/opis/closure)
[![Packagist Downloads](https://img.shields.io/packagist/dt/opis/closure?label=Downloads)](https://packagist.org/packages/opis/closure)
[![Packagist License](https://img.shields.io/packagist/l/opis/closure?color=teal&label=License)](https://packagist.org/packages/opis/closure)

Serialize closures, serialize anything
------------------

**Opis Closure** is a PHP library that allows you to serialize closures, anonymous classes, and arbitrary data.

```php
use function Opis\Closure\{serialize, unserialize};

$serialized = serialize(fn() => "hello from closure!");
$greet = unserialize($serialized);

echo $greet(); // hello from closure!
```

> [!IMPORTANT]
> Starting with version 4.2, **Opis Closure** supports serialization of anonymous classes.

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

_A full rewrite was necessary to keep this project compatible with the PHP's new features, such as attributes, enums, 
read-only properties, named parameters, anonymous classes, and so on. This wasn't an easy task, as the latest attempt 
to launch a 4.x version involved using the FFI extension in exotic ways, and it failed hard. The main problem was that 
very often the closures were bound to some object, thus in order to preserve functionality, we had to serialize the object 
too. Since we had to do arbitrary data serialization, we decided to make this project about arbitrary data serialization, 
providing support for serializing closures but also adding more effortless ways to serialize custom objects._

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
        "opis/closure": "^4.3"
    }
}
```

[documentation]: https://opis.io/closure/4.x/ "Opis Closure Documentation"
[migration]: https://opis.io/closure/4.x/migrate.html "Opis Closure Migration guide"
[license]: http://opensource.org/licenses/MIT "MIT License"
[Packagist]: https://packagist.org/packages/opis/closure "Packagist"
[Composer]: https://getcomposer.org "Composer"
[CHANGELOG]: https://github.com/opis/closure/blob/master/CHANGELOG.md "Changelog"
