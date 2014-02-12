Serializable Closure
====================
[![Build Status](https://travis-ci.org/opis/closure.png?branch=master)](https://travis-ci.org/opis/closure)
[![Latest Stable Version](https://poser.pugx.org/opis/closure/v/stable.png)](https://packagist.org/packages/opis/closure)
[![Latest Unstable Version](https://poser.pugx.org/opis/closure/v/unstable.png)](https://packagist.org/packages/opis/closure)
[![License](https://poser.pugx.org/opis/closure/license.png)](https://packagist.org/packages/opis/closure)

The real serialization of PHP closures
--------------------

Serializations of closures in the real way (like any other serializable object) is now possible, in a safe and fast way.

 * Works for any PHP version that has closures (Yes, even with PHP 5.3)
 * Supports any closure (even recursive ones) with any variables used in `use()` (the variables from `use()` must also be serializable)
 * Unserialization doesn't need `eval()`, so that any error in a closure can be caught
 * Simple and very fast parser
 * You can serialize/unserialize any closure unlimited times, even those previously unserialized (this is possible because `eval()` is not used for unserialization)
 * Provides a reflector, which can give you informations about closure code, parameters, ...
 * Supports bound objects (exists only from PHP >= 5.4)


###Installation

This library is available on [Packagist](https://packagist.org/packages/opis/closure) and can be installed using [Composer](http://getcomposer.org)

```json
{
    "require": {
        "opis/closure": "1.0.*"
    }
}
```

###Examples

Factorial example using a recursive closure

```php


require 'vendor/autoload.php';

use Opis\Closure\SerializableClosure;

// Recursive factorial closure
$factorial = function ($n) use (&$factorial) {
  return $n <= 1 ? 1 : $factorial($n - 1) * $n;
};

// Wrap the closure
$wrapper = new SerializableClosure($factorial);
// Now it can be serialized
$serialized = serialize($wrapper);
// Unserialize the closure
$wrapper = unserialize($serialized);

// You can directly invoke the wrapper...
echo $wrapper(5); //> 120

// Or, the recommended way, extract the closure object
$closure = $wrapper->getClosure();

echo $closure(5); //> 120

// Once again, but this time using the previously unserialized closure
$wrapper = new SerializableClosure($closure);
$serialized = serialize($wrapper);
$wrapper = unserialize($serialized);
$closure = $wrapper->getClosure();

// Now watch this...
echo $closure(5); //> 120
// It worked!

```

####Note
Due to PHP limitations, this library cannot detect the correct closure code if there is more then one closure on a single line.

```php

// This will NOT work!
$first = function() {return "first function";}; $second = function() {return "second function";};

// This will work!
$first = function() {return "first function";};
$second = function() {return "second function";};
```
