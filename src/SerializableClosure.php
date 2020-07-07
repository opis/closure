<?php
/* ===========================================================================
 * Copyright 2018-2020 Zindex Software
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ============================================================================ */

namespace Opis\Closure;

class SerializableClosure
{
    private static bool $init = false;

    /**
     * Preload files and ffi
     */
    public static function preload(): void
    {
        HeaderFile::preload();

        array_map(static fn (string $file) => opcache_compile_file(__DIR__ . '/' . $file), [
            'BaseClosure.php',
            'ClosureStream.php',
            'CodeWrapper.php',
            'ReflectionClosure.php',
            'ReflectionFunctionInfo.php',
            'SerializableClosureHandler.php',
            'TokenizedFileInfo.php',
        ]);
    }

    /**
     * Init serializable closures
     * @param array|null $options
     */
    public static function init(?array $options = null): void
    {
        if (self::$init) {
            return;
        }
        self::$init = true;

        SerializableClosureHandler::init(HeaderFile::load(), $options);
    }
}