Opis Closure
====================
![](https://github.com/opis/closure/workflows/Tests/badge.svg?branch=4.x)
[![Latest Stable Version](https://poser.pugx.org/opis/closure/v/stable.png)](https://packagist.org/packages/opis/closure)
[![Latest Unstable Version](https://poser.pugx.org/opis/closure/v/unstable.png)](https://packagist.org/packages/opis/closure)
[![License](https://poser.pugx.org/opis/closure/license.png)](https://packagist.org/packages/opis/closure)

Serialize anything
------------------

**Opis Closure** is a PHP library that allows you to serialize arbitrary data, 
including closures, without breaking a sweat.

```php
use Opis\Closure\Serializer;

// init lib
Serializer::init();

$serialized = Serializer::serialize(fn() => "hello!");
$greet = Serializer::unserialize($serialized);

echo $greet();
```

A full rewrite was necessary to keep this project compatible with the PHP's new features, such as 
attributes, enums, readonly properties, named parameters, anonymous classes and so on.
This wasn't an easy task, the latest attempt involved using FFI extension in exotic ways, and still failed hard.
The main problem was that very often the closures were bound to some object, thus in order to preserve functionality 
we had to serialize the object too. Since we had to do arbitrary data serialization, we decided to make this project
about arbitrary data serialization, providing support for serializing closures but also adding easier ways to
serialize custom objects.

Starting with v4.0 **Opis Closure** is about arbitrary data serialization not just closure serialization. 

## Migrating from 3.x

Version 4.x is a full rewrite of the library, and unfortunately it is not compatible with 3.x.

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
        "opis/closure": "^4.0"
    }
}
```

[documentation]: https://opis.io/closure/4.x/ "Opis Closure Documentation"
[license]: http://opensource.org/licenses/MIT "MIT License"
[Packagist]: https://packagist.org/packages/opis/closure "Packagist"
[Composer]: https://getcomposer.org "Composer"
[CHANGELOG]: https://github.com/opis/closure/blob/master/CHANGELOG.md "Changelog"
