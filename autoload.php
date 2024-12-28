<?php

require_once __DIR__ . '/src/functions.php';

spl_autoload_register(static function (string $class): bool {
    $class = ltrim($class, '\\');
    $dir = __DIR__ . '/src';
    $namespace = 'Opis\Closure';

    if (str_starts_with($class, $namespace)) {
        $class = substr($class, strlen($namespace));
        $path = '';
        if (($pos = strrpos($class, '\\')) !== false) {
            $path = str_replace('\\', '/', substr($class, 0, $pos)) . '/';
            $class = substr($class, $pos + 1);
        }
        $path .= str_replace('_', '/', $class) . '.php';
        $dir .= '/' . $path;

        if (is_file($dir)) {
            include $dir;
            return true;
        }
    }

    return false;
});
