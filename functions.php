<?php
/* ===========================================================================
 * Copyright (c) 2018-2020 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure;

function init(bool $preload = false) {
    if ($preload) {
        HeaderFile::preload();
        array_map(function (string $file) {
            opcache_compile_file(__DIR__ . '/src/' . $file);
        }, [
            'ClosureStream.php',
            'ReflectionClosure.php',
            'SerializableClosure.php',
            'SerializableClosureHandler.php',
        ]);
    } else {
        SerializableClosureHandler::init(HeaderFile::load());
    }
}
