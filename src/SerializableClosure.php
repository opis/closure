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

use DirectoryIterator;

class SerializableClosure
{
    private static bool $init = false;

    /**
     * Preload files and ffi
     */
    public static function preload(): void
    {
        // Preload FFI
        HeaderFile::preload();

        // Ignore the following files to avoid 'Can't preload already declared class ...' warnings
        $ignore = [
            'HeaderFile.php', // header file used above
            'SerializableClosure.php', // this file
        ];

        // Compile files
        foreach ((new DirectoryIterator(__DIR__)) as $fileInfo) {
            if ($fileInfo->isDot() || !$fileInfo->isFile() ||
                $fileInfo->getExtension() !== 'php' || in_array($fileInfo->getFilename(), $ignore)) {
                continue;
            }

            $script = $fileInfo->getRealPath();

            if (!$script || opcache_is_script_cached($script)) {
                continue;
            }

            opcache_compile_file($script);
        }
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

        self::defines();

        SerializableClosureHandler::init(HeaderFile::load(), $options);
    }

    protected static function defines(): void
    {
        $const = [
            'T_NAME_FULLY_QUALIFIED',
            'T_NAME_QUALIFIED',
            'T_NAME_RELATIVE',
            'T_ATTRIBUTE',
        ];

        foreach ($const as $key => $value) {
            if (!defined($value)) {
                define($value, -(100 + $key));
            }
        }
    }
}