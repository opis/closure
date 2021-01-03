<?php

/**
 * Run this code using opis/closure 3.x to get 3.x.json
 */

use Opis\Closure\SerializableClosure;

require_once 'vendor/autoload.php';


$data = ['a' => 1, 'b' => null];
$data['b'] = &$data['a'];

$simple = function (int $x, int $y) : \stdClass {
    return (object)[
        'x' => $x,
        'y' => $y,
    ];
};

$use = function (int $x, int $y) use (&$data): \stdClass {
    return (object)[
        'x' => $x,
        'y' => $y,
        'a' => $data['a'],
    ];
};

$self_ref = function (int $n) use (&$self_ref) : int {
    if ($n <= 0) {
        return 0;
    }
    return $n + $self_ref($n - 1);
};

$sqr = function (int $n) : int {
    return $n * $n;
};

$sqr_plus_1 = fn (int $n) : int => $sqr($n) + 1;

$list = [];

$list[] = [
    'name' => 'simple',
    'data' => serialize(SerializableClosure::from($simple)),
    'secret' => null,
    'call' => [1, 2],
    'expect' => (object) ['x' => 1, 'y' => 2],
];

$list[] = [
    'name' => 'use',
    'data' => serialize(SerializableClosure::from($use)),
    'secret' => null,
    'call' => [3, 4],
    'expect' => (object) ['x' => 3, 'y' => 4, 'a' => 1],
];

$list[] = [
    'name' => 'self_ref',
    'data' => serialize(SerializableClosure::from($self_ref)),
    'secret' => null,
    'call' => [100],
    'expect' => 5050,
];

$list[] = [
    'name' => 'sqr_plus_1',
    'data' => serialize(SerializableClosure::from($sqr_plus_1)),
    'secret' => null,
    'call' => [10],
    'expect' => 101,
];

$secret = 'my_secret_key';
SerializableClosure::setSecretKey($secret);

$list[] = [
    'name' => 'simple:secret',
    'data' => serialize(SerializableClosure::from($simple)),
    'secret' => $secret,
    'call' => [1, 2],
    'expect' => (object) ['x' => 1, 'y' => 2],
];

$list[] = [
    'name' => 'sqr_plus_1:secret',
    'data' => serialize(SerializableClosure::from($sqr_plus_1)),
    'secret' => $secret,
    'call' => [10],
    'expect' => 101,
];

SerializableClosure::removeSecurityProvider();


echo json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
