Opis Closure
====================
[![Build Status](https://travis-ci.org/opis/closure.png?branch=master)](https://travis-ci.org/opis/closure)
[![Latest Stable Version](https://poser.pugx.org/opis/closure/v/stable.png)](https://packagist.org/packages/opis/closure)
[![Latest Unstable Version](https://poser.pugx.org/opis/closure/v/unstable.png)](https://packagist.org/packages/opis/closure)
[![License](https://poser.pugx.org/opis/closure/license.png)](https://packagist.org/packages/opis/closure)

Serializable closures
---------------------
**Opis Closure** is a library that aims to overcome PHP's limitations regarding closure
serialization by providing a wrapper that will make all closures serializable. 

**The library's key features:**

* Serialize any closure
* Doesn't use `eval` for closure serialization or unserialization
* Works with any PHP version that has support for closures (Yes, even with PHP 5.3)
* Supports PHP 7.0 syntax
* Handles all variables referenced/imported in `use()` and automatically wraps all referenced/imported closures for
proper serialization
* Handles recursive closures
* Handles magic constants like `__FILE__`, `__DIR__`, `__LINE__`, `__NAMESPACE__`, `__CLASS__`,
`__TRAIT__`, `__METHOD__` and `__FUNCTION__`.
* Automatically resolves all class names, function names and constant names used inside the closure
* Track closure's residing source by using the `#trackme` directive
* Simple and very fast parser
* Any error or exception, that might occur when executing an unserialized closure, can be caught and treated properly
* You can serialize/unserialize any closure unlimited times, even those previously unserialized
(this is possible because `eval()` is not used for unserialization)
* Handles static closures
* Supports cryptographically signed closures
* Provides a reflector that can give you information about the serialized closure
* Provides an analyzer for *SuperClosure* library
* Automatically detects when the scope and/or the bound object of a closure needs to be serialized
in order for the closure to work after deserialization


### License

**Opis Closure** is licensed under the [MIT License (MIT)](http://opensource.org/licenses/MIT). 

### Requirements

* PHP 5.3.* or higher

### Installation

This library is available on [Packagist](https://packagist.org/packages/opis/closure) and can be installed using [Composer](http://getcomposer.org).

```json
{
    "require": {
        "opis/closure": "^2.4.1"
    }
}
```

If you are unable to use [Composer](http://getcomposer.org) you can download the
[tar.gz](https://github.com/opis/closure/archive/2.4.1.tar.gz) or the [zip](https://github.com/opis/closure/archive/2.4.1.zip)
archive file, extract the content of the archive and include de `autoload.php` file into your project. 

```php

require_once 'path/to/closure-2.4.1/autoload.php';

```

### SuperClosure support

[SuperClosure](https://github.com/jeremeamia/super_closure) is the most 
popular an widely used closure serialization library. 
Starting with version `2.2.0`, *Opis Closure* is shipped with an analyzer(`Opis\Closure\Analyzer`)
which aims to provide *Opis Closure*'s parsing precision and speed to *SuperClosure*.

### Documentation

Examples and documentation can be found [here](http://opis.io/closure)
