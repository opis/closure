Serializable Closure
====================
[![Build Status](https://travis-ci.org/opis/closure.png?branch=master)](https://travis-ci.org/opis/closure)
[![Latest Stable Version](https://poser.pugx.org/opis/closure/v/stable.png)](https://packagist.org/packages/opis/closure)
[![Latest Unstable Version](https://poser.pugx.org/opis/closure/v/unstable.png)](https://packagist.org/packages/opis/closure)
[![License](https://poser.pugx.org/opis/closure/license.png)](https://packagist.org/packages/opis/closure)

The real serialization of PHP closures
--------------------

Real serialization of closures is now possible. Serialize any closure in a safe and fast way.

 * Works for any PHP version that has support for closures (Yes, even with PHP 5.3)
 * Handles all variables referenced/imported in `use()` and automatically wraps all referenced/imported closures for proper serialization
 * Handles recursive closures
 * Unserialization doesn't need `eval()`, so that any error in a closure can be caught
 * Simple and very fast parser
 * Handles magic constants like `__FILE__`, `__DIR__`, `__LINE__`, `__NAMESPACE__`
 * You can serialize/unserialize any closure unlimited times, even those previously unserialized (this is possible because `eval()` is not used for unserialization)
 * Provides a reflector, which can give you informations about closure code, parameters, ...
 * Supports serialization of bounded objects and scopes (available only from PHP >= 5.4)


###Installation

This library is available on [Packagist](https://packagist.org/packages/opis/closure) and can be installed using [Composer](http://getcomposer.org)

```json
{
    "require": {
        "opis/closure": "1.2.*"
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

An example of a serializable class containing bounded closures (PHP >=5.4 only)

```php
require 'vendor/autoload.php';

use Opis\Closure\SerializableClosure;

// create a new instance (see class code below)
$dyn = new DynamicMethods();

// a simple sum function
$dyn->addMethod('sum', function($a, $b) {
  return $a + $b;
});

// a simple average function
$dyn->addMethod('avg', function () {
  if ($args = func_get_args()) {
    return array_sum($args) / count($args);
  }
  return 0;
});

// a function that calls another function
$dyn->addMethod('call', function($method) {
  // $this->methods is protected
  if (isset($this->methods[$method])) {
    $args = func_get_args();
    array_shift($args);
    return call_user_func_array($this->methods[$method], $args);
  }
  return false;
});

// a function that return a private property
$dyn->addMethod('answerUltimateQuestion', function() {
  // $this->the_answer is private
  return $this->the_answer;
});


// now we can serialize-unserialize the object
$newdyn = unserialize(serialize($dyn));

// test sum
echo "sum=", $newdyn->sum(5, 8), " "; //> 13
// test call with avg
echo "avg=", $newdyn->call('avg', 1, 2, 3), " "; //> 2
// now, the final answer...
echo "answer=", $newdyn->answerUltimateQuestion(), " "; // I'm curious too...

// no one should ever know the answer
$newdyn->addMethod('removeAnswer', function() {
  $this->the_answer = null;
  unset($this->methods['removeAnswer']);
  unset($this->methods['answerUltimateQuestion']);
});

$newdyn->removeAnswer();

// let's see if the answer is now secret

$otherdyn = unserialize(serialize($newdyn));

try {
  echo $otherdyn->answerUltimateQuestion(); // throws exception
}
catch (\Exception $e) {
  echo "The answer is secret!";
}

// --- class ----

class DynamicMethods implements \Serializable {
 
  protected $methods = array();
  private $the_answer = 052;
 
  public function __call($method, $args) {
    if (isset($this->methods[$method])) {
      return call_user_func_array($this->methods[$method], $args);
    }
    throw new \Exception("The method $method doesn't exists!");
  }
 
  public function addMethod($name, \Closure $func) {
    $func = $func->bindTo($this, $this);
    $this->methods[$name] = $func;
    return $func;
  }
 
  public function serialize() {
    $methods = array();
    foreach ($this->methods as $name => $method) {
      $methods[$name] = new SerializableClosure($method, true);
    }
    return serialize(array(
      'methods' => $methods,
      'answer' => $this->the_answer,
    ));
  }
 
  public function unserialize($data) {
    $data = unserialize($data);
    $this->the_answer = $data['answer'];
    foreach ($data['methods'] as $name => $method) {
      $this->methods[$name] = $method->getClosure();
    }
  }
 
}
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
