Serializable closures
====================
[![Build Status](https://travis-ci.org/opis/closure.png?branch=master)](https://travis-ci.org/opis/closure)
[![Latest Stable Version](https://poser.pugx.org/opis/closure/v/stable.png)](https://packagist.org/packages/opis/closure)
[![Latest Unstable Version](https://poser.pugx.org/opis/closure/v/unstable.png)](https://packagist.org/packages/opis/closure)
[![License](https://poser.pugx.org/opis/closure/license.png)](https://packagist.org/packages/opis/closure)

Serialization of PHP closures
--------------------

If you ever used closures then you probably know that trying to serialize a closure will result in an exception.

```php
Fatal error: Uncaught exception 'Exception' with message 'Serialization of 'Closure' is not allowed'
```
This library aims to overcome PHP's limitations regarding closure serialization by providing a wrapper that will make the closure serializable.

**The library's key features:**
 
 * Serialize any closure
 * Doesn't use `eval` for closure serialization or unserialization
 * Works with any PHP version that has support for closures (Yes, even with PHP 5.3)
 * Handles all variables referenced/imported in `use()` and automatically wraps all referenced/imported closures for proper serialization
 * Handles recursive closures
 * Handles magic constants like `__FILE__`, `__DIR__`, `__LINE__`, `__NAMESPACE__`
 * Simple and very fast parser
 * Any error or exception, that might occur when executing an unserialized closure, can be caught and treated properly
 * You can serialize/unserialize any closure unlimited times, even those previously unserialized (this is possible because `eval()` is not used for unserialization)
 * Provides a reflector that can give you informations about closure code's, parameters, ...
 * Supports serialization of bounded objects and scopes (available only from PHP >= 5.4)


### Installation

This library is available on [Packagist](https://packagist.org/packages/opis/closure) and can be installed using [Composer](http://getcomposer.org)

```json
{
    "require": {
        "opis/closure": "1.2.*"
    }
}
```

## Examples and documentation

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

### Serialization contexts

Let's take the following example

```php

require 'vendor/autoload.php';

use Opis\Closure\SerializableClosure;

$function = function(){
    return "Hello World";
};

$collection = array(
    'a' => new SerializableClosure($function),
    'b' => new SerializableClosure($function),
);

//Serialize
$collection = serialize($collection);
//Unserialize
$collection = unserialize($collection);

//Outputs FALSE
print $collection['a']->getClosure() === $colection['b']->getClosure() ? 'TRUE' : 'FALSE';

```
In the above example, even though the same closure instance was serialized, after the deserialization, two different instances of the same closure were created.
This happened because the same closure was wrapped by two different `SerializableClosure` objects.
To fix this issue, we must rewrite our code:

```php

require 'vendor/autoload.php';

use Opis\Closure\SerializableClosure;

$function = function(){
    return "Hello World";
};

SerializableClosure::enterContext();

$collection = array(
    'a' => SerializableClosure::from($function),
    'b' => SerializableClosure::from($function),
);

SerializableClosure::exitContext();

//Serialize
$collection = serialize($collection);
//Unserialize
$collection = SerializableClosure::unserializeData($collection);

//Outputs TRUE
print $collection['a']->getClosure() === $colection['b']->getClosure() ? 'TRUE' : 'FALSE';
```
Now let's analyze the above code:

 * **`SerializableClosure::enterContext`**
    
Creates a new serialization context, if needed.
If a context was already created by a previous call to this method,
the context is reentered recursively by incrementing its internal counter.
Each call to `SerializableClosure::enterContext` must have a matching call to `SerializableClosure::exitContext` method.
 * **`SerializableClosure::from`**
 
    Wraps a given closure inside a `SerializableClosure` object, keeping a record of all closures that
    were wrapped in the current context. If a closure was already wrapped, it returns the coresponding `SerializableClosure` object.
    This method is an equivalent of `new SerializableClosure`
    and it can be used even if a serialization context wasn't created.
 * **`SerializableClosure::exitContext`**
 
    Exit from a serialization context by decrementing its internal counter.
    When the context's counter reaches to zero, the context is destroyed.
 * **`SerializableClosure::unserializeData`**
    
    This method is an equivalent of PHP's `unserialize()` function and its sole purpose is to overcome some of the bugs found PHP 5.3.
    The usage of this method is not mandatory if you are planning to use this library with PHP 5.4 or latest

### Bounded objects

Starting with PHP 5.4 the `$this` keyword can be used inside a closure's body if the closure was bound to an object.
If you want to serialize the bounded object too, the only thing you are required to do is to pass
`true` as the second parameter to the `SerializableClosure` constructor or to the `SerializableClosure::from` method.

```php

$wrapper = new SerializableClosure($closure, true);
//or
$wrapper = SerializableClosure::from($closure, true);

```

An example of a serializable class containing bounded closures (PHP >=5.4 only)

```php

require 'vendor/autoload.php';

use Opis\Closure\SerializableClosure;

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

// create a new instance
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

```

#### Note
Due to PHP limitations, this library cannot detect the correct closure code if there is more then one closure on a single line.

```php

// This will NOT work!
$first = function() {return "first function";}; $second = function() {return "second function";};

// This will work!
$first = function() {return "first function";};
$second = function() {return "second function";};
```
