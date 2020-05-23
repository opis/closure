<?php

namespace Opis\Closure;

use FFI, FFI\Exception as FFIException;

class HeaderFile
{
    /**
     * FFI_SCOPE_NAME
     */
    protected const SCOPE_NAME = 'OpisClosure';

    /**
     * WIN detector
     */
    protected const IS_WIN = \DIRECTORY_SEPARATOR === '\\';

    /**
     * FFI_LIB_NAME
     */
    protected const LIB_NAME = self::IS_WIN
        ? 'php' . \PHP_MAJOR_VERSION . (\PHP_ZTS ? 'ts' : '') . (\PHP_DEBUG ? '_debug' : '') . '.dll'
        : '';

    /**
     * Preprocess regex
     */
    protected const PREPROCESS_REGEX = '/^\s*#(?<if>ifn?def)\s+(?<cond>.+?)\s*(?<then>^.+?)(?:^\s*#else\s*(?<else>^.+?))?^\s*#endif\s*/sm';

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

        $data = static::content($defs, $file);

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
        file_put_contents($tmp, static::content($defs, $file));

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
    public static function content(array $defs = [], ?string $file = null): string
    {
        if ($file === null) {
            $file = __DIR__ . '/../include/headers.h';
        }

        $defs += static::defs();

        return self::preprocess(file_get_contents($file), $defs + static::defs());
    }

    /**
     * @return array Definitions
     */
    public static function defs(): array
    {
        $defs = [
            'FFI_SCOPE_NAME' => self::SCOPE_NAME,
            'FFI_LIB_NAME' => self::LIB_NAME,

            'ZEND_API' => '__declspec(dllimport)',
            'ZEND_FASTCALL' => self::IS_WIN ? '__vectorcall' : '',
            'ZEND_MAX_RESERVED_RESOURCES' => 6,
        ];

        if (\ZEND_THREAD_SAFE) {
            $defs['ZTS'] = 1;
        }

        if (self::IS_WIN) {
            $defs['ZEND_WIN32'] = 1;
        }

        if (\PHP_INT_SIZE === 8) {
            $defs['PLATFORM_64'] = 1;
        } else {
            $defs['PLATFORM_32'] = 1;
        }

        return $defs;
    }

    /**
     * @param string $data Unprocessed content
     * @param array $defs Definitions
     * @return string Processed content
     */
    public static function preprocess(string $data, array $defs = []): string
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

        $data = strtr($data, $defs);

        return $data;
    }
}