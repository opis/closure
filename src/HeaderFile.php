<?php
/* ===========================================================================
 * Copyright 2020-2021 Zindex Software
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

use FFI, FFI\Exception as FFIException, RuntimeException;
use const PHP_DEBUG, PHP_ZTS;
use const DIRECTORY_SEPARATOR, ZEND_THREAD_SAFE;
use const PHP_INT_SIZE, PHP_MAJOR_VERSION, PHP_MINOR_VERSION;

/**
 * @internal
 */
final class HeaderFile
{
    /**
     * FFI_SCOPE_NAME
     */
    private const SCOPE_NAME = 'OpisClosure';

    /**
     * WIN detector
     */
    private const IS_WIN = DIRECTORY_SEPARATOR === '\\';

    /**
     * FFI_LIB_NAME
     */
    private const LIB_NAME = self::IS_WIN
        ? 'php' . PHP_MAJOR_VERSION . (PHP_ZTS ? 'ts' : '') . (PHP_DEBUG ? '_debug' : '') . '.dll'
        : '';

    /**
     * Preprocess regex
     */
    private const PREPROCESS_REGEX = '/^\s*#(?<if>ifn?def)\s+(?<cond>.+?)\s*(?<then>^.+?)(?:^\s*#else\s*(?<else>^.+?))?^\s*#endif\s*/sm';

    /**
     * @param array $defs
     * @param string|null $file
     * @return FFI
     */
    public static function load(array $defs = [], ?string $file = null): FFI
    {
        try {
            return FFI::scope(self::SCOPE_NAME);
        } catch (FFIException $e) {}

        $data = self::content($defs, $file);

        if (self::LIB_NAME) {
            return FFI::cdef($data, self::LIB_NAME);
        }

        return FFI::cdef($data);
    }

    /**
     * @param array $defs
     * @param string|null $file
     * @return FFI
     */
    public static function preload(array $defs = [], ?string $file = null): FFI
    {
        $tmp = tempnam(sys_get_temp_dir(), 'opis_closure_ffi_');

        if (!file_put_contents($tmp, self::content($defs, $file))) {
            throw new RuntimeException("Cannot write header file: {$tmp}");
        }

        try {
            return FFI::load($tmp);
        } finally {
            unlink($tmp);
        }
    }

    /**
     * @param array $defs
     * @param string|null $file
     * @return string
     */
    private static function content(array $defs = [], ?string $file = null): string
    {
        if ($file === null) {
            $file = __DIR__ . '/../include/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.h';
        }

        if (!is_file($file)) {
            throw new RuntimeException("File not found: {$file}");
        }

        $defs += self::defs();

        return self::preprocess(file_get_contents($file), $defs + self::defs());
    }

    /**
     * @return array Definitions
     */
    private static function defs(): array
    {
        $defs = [
            'FFI_SCOPE_NAME' => self::SCOPE_NAME,

            'ZEND_API' => '__declspec(dllimport)',
            'ZEND_FASTCALL' => self::IS_WIN ? '__vectorcall' : '',
            'ZEND_MAX_RESERVED_RESOURCES' => 6,
        ];

        if (self::LIB_NAME) {
            $defs['FFI_LIB_NAME'] = self::LIB_NAME;
        }

        if (ZEND_THREAD_SAFE) {
            $defs['ZTS'] = 1;
        }

        if (self::IS_WIN) {
            $defs['ZEND_WIN32'] = 1;
        }

        if (PHP_INT_SIZE === 8) {
            $defs['PLATFORM_64'] = 1;
        } else {
            $defs['PLATFORM_32'] = 1;
        }

        // $defs['PHP_MAJOR_VERSION_' . PHP_MAJOR_VERSION] = 1;
        // $defs['PHP_VERSION_' . PHP_MAJOR_VERSION . '_' . PHP_MINOR_VERSION] = 1;

        return $defs;
    }

    /**
     * @param string $data Unprocessed content
     * @param array $defs Definitions
     * @return string Processed content
     */
    private static function preprocess(string $data, array $defs = []): string
    {
        $data = preg_replace_callback(self::PREPROCESS_REGEX, function (array $m) use (&$defs) {
            $ok = array_key_exists($m['cond'], $defs);
            if ($m['if'] === 'ifndef') {
                $ok = !$ok;
            }
            if ($ok) {
                return $m['then'];
            }
            return $m['else'] ?? '';
        }, $data);

        return strtr($data, $defs);
    }
}