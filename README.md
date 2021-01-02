Opis Closure
====================
![](https://github.com/opis/closure/workflows/Tests/badge.svg?branch=4.x)
[![Latest Stable Version](https://poser.pugx.org/opis/closure/v/stable.png)](https://packagist.org/packages/opis/closure)
[![Latest Unstable Version](https://poser.pugx.org/opis/closure/v/unstable.png)](https://packagist.org/packages/opis/closure)
[![License](https://poser.pugx.org/opis/closure/license.png)](https://packagist.org/packages/opis/closure)

Serializable closures
---------------------
> **Note:** Opis Closure 4.x is still in development and we do not recommend using it in production yet.

**Opis Closure** is a PHP library that allows you to serialize closures without breaking a sweat. 
All you have to do is to add a single line of code, and you are good to go.

```php
\Opis\Closure\Library::init();
```

If you are using this library in a server environment, and you have preload enabled (which you should), then 
add the following line of code in your preload file:

```php
\Opis\Closure\Library::preload();
```

Now you can serialize/unserialize closures the same way you would serialize/unserialize any other data structure.

```php
$f = fn() => 'Hello';
$data = serialize($f);
$g = unserialize($data);
echo $g(); //> Hello
```

This version of **Opis Closure** is a full rebuild of the library and is not compatible with the previous versions.
The library use [FFI] to make closures serializable and you no longer need to wrap them as it was the case in the past.

We would like to release a stable version as soon as possible, 
so please test it rigorously and report any errors you have encountered. Feedback is welcome.

## License

**Opis Closure** is licensed under the [Apache License, Version 2.0][license].

## Requirements

* PHP ^7.4 | ^8.0
* FFI

## Installation

**Opis Closure** is available on [Packagist] and it can be installed from a 
command line interface by using [Composer]. 

```bash
composer require opis/closure:4.x-dev
```

Or you could directly reference it into your `composer.json` file as a dependency

```json
{
    "require": {
        "opis/closure": "4.x-dev"
    }
}
```


[documentation]: https://www.opis.io/closure "Opis Closure"
[license]: https://www.apache.org/licenses/LICENSE-2.0 "Apache License"
[Packagist]: https://packagist.org/packages/opis/closure "Packagist"
[Composer]: https://getcomposer.org "Composer"
[CHANGELOG]: https://github.com/opis/closure/blob/master/CHANGELOG.md "Changelog"
[FFI]: https://www.php.net/manual/en/book.ffi.php "Foreign Function Interface"