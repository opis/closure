<?php

namespace Opis\Closure;

/**
 * @internal
 */
abstract class AbstractParser
{
    /**
     * @var int number of tokens
     */
    protected int $count;

    /**
     * @var array Import hints
     */
    protected array $hints = [];

    /**
     * @var int Current token index
     */
    protected int $index = -1;

    protected function __construct(
        /**
         * @var string Namespace
         */
        protected string $ns,
        /**
         * @var array|null Import aliases
         */
        protected ?array $aliases,
        /**
         * @var array Readonly file tokens
         */
        protected array  $tokens,
        /**
         * @var array Anonymous classes token bounds
         */
        protected array $anonymous,
    )
    {
        // Set number of tokens
        $this->count = count($tokens);
    }

    protected function between(int $index, int $end): string
    {
        $hint = '';
        $code = '';
        $tokens = $this->tokens;
        $use_hints = $this->aliases !== null;

        while ($index <= $end) {
            $token = $tokens[$index++];
            $code .= is_array($token) ? $token[1] : $token;

            if ($use_hints) {
                switch ($token[0]) {
                    case T_STRING:
                    case T_NS_SEPARATOR:
                    case T_NAME_QUALIFIED:
                    case T_NAME_FULLY_QUALIFIED:
                        $hint .= $token[1];
                        break;
                    case T_WHITESPACE:
                    case T_COMMENT:
                    case T_DOC_COMMENT:
                        // ignore whitespace and comments
                        break;
                    default:
                        if ($hint !== '') {
                            $this->addHint($hint);
                            $hint = '';
                        }
                        break;
                }
            }
        }

        if ($use_hints && $hint !== '') {
            $this->addHint($hint);
        }

        return $code;
    }

    /**
     * @param int $index
     * @param array $token_types
     * @param bool $match Pass false to skip, pass true to match first
     * @param int $maxLine
     * @return int
     */
    protected function walk(int $index, array $token_types, bool $match = false, int $maxLine = PHP_INT_MAX): int
    {
        $count = $this->count;
        $tokens = $this->tokens;

        do {
            $is_arr = is_array($tokens[$index]);
            if (
                $index >= $count || // too big
                ($is_arr && $tokens[$index][2] > $maxLine) // past max line
            ) {
                return -1;
            }
            if ($match === in_array($is_arr ? $tokens[$index][0] : $tokens[$index], $token_types, true)) {
                return $index;
            }
            $index++;
        } while (true);
    }

    /**
     * Add import hint
     * @param string $hint
     * @return bool True if hint was added
     */
    protected function addHint(string $hint): bool
    {
        if (!$hint || $hint[0] == '\\') {
            // Ignore empty or absolute
            return false;
        }

        $key = strtolower($hint);

        if (isset($this->hints[$key]) || in_array($key, self::$BUILTIN_TYPES)) {
            return false;
        }

        $this->hints[$key] = $hint;

        return true;
    }

    /**
     * Get namespace and formatted imports
     * @return string
     */
    protected function getHeader(): string
    {
        $ns = $this->ns;

        if ($this->aliases || $this->hints) {
            $code = self::formatImports($this->aliases, $this->hints, $ns);
        } else {
            $code = "";
        }

        if ($ns) {
            return "namespace {$ns};" . ($code ? "\n" : "") . $code;
        }

        return $code;
    }

    abstract protected function getBody(): string;

    abstract public function getInfo(): ?AbstractInfo;

    ////////////////////////////////////////////////////

    /**
     * @var AbstractInfo[]
     */
    private static array $cache = [];

    private static array $fileCache = [];

    final public static function clear(bool $include_file_cache = false): void
    {
        self::$cache = [];
        if ($include_file_cache) {
            self::$fileCache = [];
        }
    }

    abstract public static function parse($reflector): ?AbstractInfo;

    abstract protected static function create(
        $reflector,
        string $ns,
        array $fileInfo,
        ?array $aliases
    ): static;

    protected static function resolve(
        \ReflectionClass|\ReflectionFunction $reflector,
        string                               $prefix
    ): ?AbstractInfo
    {
        // Get file name
        $file = $reflector->getFileName();

        // Check if file name is present
        if (!$file) {
            return null;
        }

        // Try already deserialized
        // closure://...
        if ($fromStream = CodeStream::info($file)) {
            return $fromStream;
        }

        // Get file key
        $fileKey = md5($file);

        // Get line bounds
        $startLine = $reflector->getStartLine();
        $endLine = $reflector->getEndLine();

        // compute top-level cache key
        $cacheKey = "{$prefix}/{$fileKey}/{$startLine}/{$endLine}";

        // check info cache
        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        // check file cache
        if (!array_key_exists($fileKey, self::$fileCache)) {
            self::$fileCache[$fileKey] = TokenizedFileInfo::getInfo($file);
        }

        $fileInfo = self::$fileCache[$fileKey];
        if ($fileInfo === null) {
            return self::$cache[$cacheKey] = null;
        }

        $ns = '';
        $aliases = null;
        if ($fileInfo['namespaces']) {
            if ($info = self::findNamespaceAliases($fileInfo['namespaces'], $startLine)) {
                $ns = trim($info['ns'] ?? '', '\\');
                $aliases = $info['use'] ?? null;
            }
        }

        // cache parsed result
        return self::$cache[$cacheKey] = static::create($reflector, $ns, $fileInfo, $aliases)->getInfo();
    }

    private static function findNamespaceAliases(array $namespaces, int $startLine): ?array
    {
        foreach ($namespaces as $info) {
            if ($startLine >= $info['start'] && $startLine <= $info['end']) {
                return $info;
            }
        }
        return null;
    }

    /////////////////////////////////////////

    private const FORMAT_IMPORTS_MAP = [
        'class' => 'use ',
        'func' => 'use function ',
        'const' => 'use const ',
    ];

    private static function formatImports(array $alias, array $hints, string $ns = ""): string
    {
        if ($ns && $ns[0] !== '\\') {
            $ns = '\\' . $ns;
        }

        $use = [];

        foreach ($hints as $hint => $hintValue) {
            if (($pos = strpos($hint, '\\')) !== false) {
                // Relative
                $hint = substr($hint, 0, $pos);
                $hintValue = substr($hintValue, 0, $pos);
            }

            foreach ($alias as $type => $values) {
                if (!isset($values[$hint])) {
                    continue;
                }

                if (strcasecmp($ns . '\\' . $hint, $values[$hint]) === 0) {
                    // Skip redundant import
                    continue;
                }

                $use[$type][$hintValue] = $values[$hint];
            }
        }

        if (!$use) {
            return '';
        }

        $code = '';

        foreach (self::FORMAT_IMPORTS_MAP as $key => $prefix) {
            if (!isset($use[$key])) {
                continue;
            }
            if ($add = self::formatUse($prefix, $use[$key])) {
                if ($code) {
                    $code .= "\n";
                }
                $code .= $add;
            }
        }

        return $code;
    }

    private static function formatUse(string $prefix, ?array $items): string
    {
        if (!$items) {
            return '';
        }

        foreach ($items as $alias => $full) {
            if (strcasecmp('\\' . $alias, substr($full, 0 - strlen($alias) - 1)) === 0) {
                // Same name as alias, do not use as
                $items[$alias] = trim($full, '\\');
            } else {
                $items[$alias] = trim($full, '\\') . ' as ' . $alias;
            }
        }

        sort($items);

        return $prefix . implode(",\n" . str_repeat(' ', strlen($prefix)), $items) . ";";
    }

    private static array $BUILTIN_TYPES;

    private static function getBuiltInTypes(): array
    {
        // PHP 8

        $types = [
            'bool', 'int', 'float', 'string', 'array',
            'object', 'iterable', 'callable', 'void', 'mixed',
            'self', 'parent', 'static',
            'false', 'null',
        ];

        if (PHP_MINOR_VERSION >= 1) {
            $types[] = 'never';
        }

        if (PHP_MINOR_VERSION >= 2) {
            $types[] = 'true';
        }

        return $types;
    }

    final public static function init(): void
    {
        self::$BUILTIN_TYPES ??= self::getBuiltInTypes();
    }
}