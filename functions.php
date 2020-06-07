<?php
/* ===========================================================================
 * Copyright (c) 2018-2020 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure;

function init(bool $preload = false): void {
    static $init = false;

    if ($init) {
        return;
    }

    $init = true;

    if ($preload) {
        HeaderFile::preload();
        array_map(fn (string $file) => opcache_compile_file(__DIR__ . '/src/' . $file), [
            'ClosureStream.php',
            'CodeWrapper.php',
            'ReflectionClosure.php',
            'ReflectionFunctionInfo.php',
            'SerializableClosure.php',
            'SerializableClosureHandler.php',
            'TokenizedFileInfo.php',
        ]);
    } else {
        SerializableClosureHandler::init(HeaderFile::load());
    }
}
