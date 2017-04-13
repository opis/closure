CHANGELOG
---------
### v2.4.1, 2017.04.13

* Fixed a bug in 'ignore_next' state

### v2.4.0, 2016.12.16

* The parser was refactored and improved
* Refactored `Opis\Closure\SerializableClosure::__invoke` method
* `Opis\Closure\{ISecurityProvider, SecurityProvider}` were added
* `Opis\Closure\{SecurityProviderInterface, DefaultSecurityProvider, SecureClosure}` were deprecated
and they will be removed in the next major version
* `setSecretKey` and `addSecurityProvider` static methods were added to `Opis\Colibri\SerializableClosure`

### v2.3.2, 2016.12.15

* Fixed a bug that prevented namespace resolution to be done properly

### v2.3.1, 2016.12.13

* Hotfix. See [PR](https://github.com/opis/closure/pull/7)

### v2.3.0, 2016.11.17

* Added `isBindingRequired` and `isScopeRequired` to the `Opis\Closure\ReflectionClosure` class
* Automatically detects when the scope and/or the bound object of a closure needs to be serialized.

### v2.2.1, 2016.08.20

* Fixed a bug in `Opis\Closure\ReflectionClosure::fetchItems`

### v2.2.0, 2016.07.26

* Fixed CS
* `Opis\Closure\ClosureContext`, `Opis\Closure\ClosureScope`, `Opis\Closure\SelfReference`
 and `Opis\Closure\SecurityException` classes were moved into separate files
* Added support for PHP7 syntax
* Fixed some bugs in `Opis\Closure\ReflectionClosure` class
* Improved closure parser
* Added an analyzer for SuperClosure library

### v2.1.0, 2015.09.30

* Added support for the missing `__METHOD__`, `__FUNCTION__` and `__TRAIT__` magic constants
* Added some security related classes and interfaces: `Opis\Closure\SecurityProviderInterface`,
`Opis\Closure\DefaultSecurityProvider`, `Opis\Closure\SecureClosure`, `Opis\Closure\SecurityException`.
* Fiexed a bug in `Opis\Closure\ReflectionClosure::getClasses` method
* Other minor bugfixes
* Added support for static closures
* Added public `isStatic` method to `Opis\Closure\ReflectionClosure` class


### v2.0.1, 2015.09.23

* Removed `branch-alias` property from `composer.json`
* Bugfix. See [issue #6](https://github.com/opis/closure/issues/6)

### v2.0.0, 2015.07.31

* The closure parser was improved
* Class names are now automatically resolved
* Added support for the `#trackme` directive which allows tracking closure's residing source

### v1.3.0, 2014.10.18

* Added autoload file
* Changed README file

### Opis Closure 1.2.2

* Started changelog
