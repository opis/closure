<?php

namespace Opis\Closure;

use ReflectionFunction;

/**
 * @internal
 */
final class ClosureParser
{
    private const SKIP_WHITESPACE_AND_COMMENTS = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    private const MATCH_CLOSURE = [T_FN, T_FUNCTION];

    /**
     * @var array Transformed file tokens cache
     */
    private static array $globalFileCache = [];

    /**
     * @var array Closure info cache by file/stat-line/end-line
     */
    private static array $globalInfoCache = [];

    /**
     * @var string[] PHP types
     */
    private static ?array $BUILTIN_TYPES = null;

    private int $count;
    private int $index = -1;

    private bool $isShort = false;
    private bool $isStatic = false;
    private bool $scopeRef = false;
    private bool $thisRef = false;

    private array $use = [];
    private array $hints = [];

    private function __construct(
        private ReflectionFunction $reflector,
        private array $tokens,
        private string $ns,
        private ?array $aliases,
        private array $anonymous
    )
    {
        $this->count = count($tokens);
    }

    /**
     * @return ClosureInfo|null
     */
    private function info(): ?ClosureInfo
    {
        $this->index = $this->functionIndex();

        if ($this->index < 0) {
            return null;
        }

        $this->filterAnonymous($this->index);

        // we must get the code first
        $code = $this->sourceCode();
        // only then we can process the imports
        $imports = $this->imports();

        return new ClosureInfo(
            $imports,
            $code,
            $this->use ?: null,
            ClosureInfo::flags(
                $this->isShort,
                $this->isStatic,
                $this->thisRef,
                $this->scopeRef,
            ),
        );
    }

    /**
     * @param int $index
     */
    private function filterAnonymous(int $index): void
    {
        if (!$this->anonymous) {
            return;
        }

        $this->anonymous = array_filter(
            $this->anonymous,
            static fn(array $bound) => $bound[0] >= $index && $index <= $bound[1]
        );
    }

    /**
     * @return int
     */
    private function functionIndex(): int
    {
        $startLine = $this->reflector->getStartLine();
        $tokens = $this->tokens;

        $index = -1; // Function index (function|fn)

        foreach ($tokens as $key => $token) {
            if (is_array($token) && $token[2] >= $startLine) {
                $index = $key;
                break;
            }
        }

        if ($index === -1) {
            // Function not found
            return -1;
        }

        // Search for T_FUNCTION / T_FN

        $count = $this->count;
        $endLine = $this->reflector->getEndLine();

        $function_skip = null;

        do {
            $func_index = $this->walk(
                $index,
                self::MATCH_CLOSURE,
                true,
                $endLine,
            );

            if ($func_index < 0) {
                return -1;
            }

            if ($tokens[$func_index][0] === T_FN) {
                // short form, definitely a closure
                return $func_index;
            }

            // we have a T_FUNCTION
            // here we must handle this case (oneliner): function /* asd */ & /** assd *// my_func() { return function() {return 1;}; }

            $function_skip ??= array_merge(
                self::SKIP_WHITESPACE_AND_COMMENTS,
                [T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, '&'],
            );

            $next = $this->walk(
                $func_index + 1,
                $function_skip,
                false,
                $endLine
            );

            if ($next < 0) {
                // we are out of tokens
                return -1;
            }

            if ($tokens[$next][0] === '(') {
                // yes, anonymous function
                return $func_index;
            }

            // check next function
            $index = $func_index + 1;
        } while ($index < $count);

        return -1;
    }

    /**
     * @return string
     */
    private function sourceCode(): string
    {
        $tokens = $this->tokens;
        $count = $this->count;
        $index = &$this->index;

        // Check if short
        $this->isShort = $tokens[$index][0] === T_FN;

        // Check for attributes and static keyword
        $start_index = $index;

        $balance = 0;
        while (--$start_index >= 0) {
            if ($balance === 0) {
                switch ($tokens[$start_index][0]) {
                    /** @noinspection PhpMissingBreakStatementInspection */
                    case T_STATIC:
                        // Mark as static
                        $this->isStatic = true;
                    // Fall
                    case T_WHITESPACE:
                    case T_COMMENT:
                    case T_DOC_COMMENT:
                        continue 2;
                    case ']':
                        if (T_ATTRIBUTE > 0) {
                            $balance--;
                            continue 2;
                        }
                }
            } else {
                switch ($tokens[$start_index][0]) {
                    case ']':
                        $balance--;
                        continue 2;
                    case T_ATTRIBUTE:
                    case '[':
                        $balance++;
                        continue 2;
                    default:
                        continue 2;
                }
            }

            $start_index++;
            break;
        }

        if ($balance === 0) {
            // Skip whitespace from start
            while ($tokens[$start_index][0] === T_WHITESPACE) $start_index++;
            $code = $this->between($start_index, $index);
        } else {
            $code = $tokens[$index][1];
        }

        // Function start
        do {
            $token = $tokens[++$index];
            if ($token === '(') {
                break;
            }
            $code .= is_array($token) ? $token[1] : $token;
        } while ($index < $count);

        // Function args
        if ($this->reflector->getNumberOfParameters() > 0) {
            $code .= $this->balance('(', ')');
        } else {
            // Skip empty args
            do {
                $token = $tokens[$index];
                $code .= is_array($token) ? $token[1] : $token;
            } while (++$index < $count && $token !== ')');
        }

        // Function use()
        if (!$this->isShort) {
            // search for T_USE

            $found = false;

            do {
                $token = $tokens[$index++];
                switch ($token[0]) {
                    case '{':
                        $index--;
                        break 2;
                    case ':':
                        $code .= $token;
                        break 2;
                    case T_USE:
                        $code .= $token[1];
                        $found = true;
                        break 2;
                    default:
                        $code .= is_array($token) ? $token[1] : $token;
                }
            } while ($index < $count);

            if ($found) {
                do {
                    $token = $tokens[$index++];
                    switch ($token[0]) {
                        case ')':
                            $code .= $token;
                            // we are done
                            break 2;
                        /** @noinspection PhpMissingBreakStatementInspection */
                        case T_VARIABLE:
                            $this->use[] = substr($token[1], 1);
                        default:
                            $code .= is_array($token) ? $token[1] : $token;
                            break;
                    }
                } while ($index < $count);
            }
        }

        // Function return type

        $has_ret_type = $this->reflector->hasReturnType();

        if ($has_ret_type) {
            $code .= $this->balance(null, $this->isShort ? T_DOUBLE_ARROW : '{', 1);
        } elseif ($this->isShort) {
            do {
                $token = $tokens[$index++];

                $code .= is_array($token) ? $token[1] : $token;

                if ($token[0] === T_DOUBLE_ARROW) {
                    break;
                }
            } while ($index < $count);
        }

        if ($this->isShort) {
            $code .= $this->balanceExpression($this->reflector->getEndLine());
        } else {
            $code .= $this->balance(['{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], '}', $has_ret_type ? 1 : 0);
        }

        return $code;
    }

    /**
     * @returns string
     */
    private function imports(): string
    {
        $code = "";
        $ns = $this->ns;

        if ($this->aliases || $this->hints) {
            $code = self::formatImports($this->aliases, $this->hints, $ns);
        }

        if ($ns) {
            return "namespace {$ns};" . ($code ? "\n" : "") . $code;
        }

        return $code;
    }

    /**
     * @param int $end_line
     * @return string
     */
    private function balanceExpression(int $end_line): string
    {
        $tokens = $this->tokens;
        $count = $this->count;
        $index = &$this->index;

        $hint = '';
        $use_hints = $this->aliases !== null;

        $code = '';

        $open_curly = 0;
        $open_square = 0;
        $open_round = 0;

        $ternary_q = [];

        do {
            $add_hint = true;
            $token = $tokens[$index++];
            $is_array = is_array($token);

            if ($is_array && $token[2] > $end_line) {
                // end line
                break;
            }

            switch ($token[0]) {
                case T_STRING:
                case T_NS_SEPARATOR:
                case T_NAME_QUALIFIED:
                case T_NAME_FULLY_QUALIFIED:
                    if ($use_hints) {
                        $hint .= $token[1];
                        $add_hint = false;
                    }
                    break;
                // Open/close
                case T_CURLY_OPEN:
                case T_DOLLAR_OPEN_CURLY_BRACES:
                case '{':
                    $open_curly++;
                    break;
                case '}':
                    if ($open_curly === 0) {
                        // Stop!
                        break 2;
                    }
                    $open_curly--;
                    break;
                case T_ATTRIBUTE:
                case '[':
                    $open_square++;
                    break;
                case ']':
                    $open_square--;
                    if ($open_square < 0) {
                        // Stop
                        break 2;
                    }
                    break;
                case '(':
                    $open_round++;
                    break;
                case ')':
                    $open_round--;
                    if ($open_round < 0) {
                        // Stop
                        break 2;
                    }
                    break;
                case '?':
                    $ternary_q[] = $open_round;
                    break;
                case ':':
                    if ($ternary_q) {
                        if ($open_round === end($ternary_q)) {
                            array_pop($ternary_q);
                        } else {
                            // this is a label
                            $hint = '';
                            $add_hint = false;
                        }
                    } else {
                        if (!$open_round) {
                            // no more labels. stop
                            break 2;
                        }
                        // these are labels
                        $hint = '';
                        $add_hint = false;
                    }
                    break;

                // Delimiters
                case ',':
                case ';':
                    if ($open_curly <= 0 && $open_round === 0 && $open_square === 0) {
                        break 2;
                    }
                    break;
                case T_WHITESPACE:
                case T_COMMENT:
                case T_DOC_COMMENT:
                    // do not add hint yet
                    $add_hint = false;
                    break;
            }

            if ($is_array) {
                $this->checkSpecialToken($index - 1);
                $code .= $token[1];
            } else {
                $code .= $token;
            }

            if ($use_hints && $add_hint && $hint !== '') {
                $this->addHint($hint);
                $hint = '';
            }

        } while ($index < $count);

        if ($use_hints && $hint !== '') {
            $this->addHint($hint);
        }

        return $code;
    }

    /**
     * @param $start
     * @param $end
     * @param int $open
     * @return string
     */
    private function balance($start, $end, int $open = 0): string
    {
        $tokens = $this->tokens;
        $count = $this->count;
        $index = &$this->index;

        $hint = '';
        $code = '';

        $is_array_start = is_array($start);
        $use_hints = $this->aliases !== null;

        $ternary_q = [];
        $goto = false;
        $open_round = 0;

        do {
            $token = $tokens[$index++];
            $is_array = is_array($token);
            $code .= $is_array ? $token[1] : $token;

            if ($is_array_start ? in_array($token[0], $start, true) : $token[0] === $start) {
                $open++;
            } elseif ($token[0] === $end) {
                if (--$open === 0) {
                    break;
                }
            } elseif ($is_array) {
                $this->checkSpecialToken($index - 1);
            }

            if ($use_hints) {
                $add_hint = true;

                switch ($token[0]) {
                    case T_STRING:
                        if ($goto) {
                            $goto = false;
                            $hint = '';
                        } else {
                            $hint .= $token[1];
                        }
                        $add_hint = false;
                        break;
                    case T_NS_SEPARATOR:
                    case T_NAME_QUALIFIED:
                    case T_NAME_FULLY_QUALIFIED:
                        $goto = false;
                        $hint .= $token[1];
                        $add_hint = false;
                        break;
                    case T_GOTO:
                        $goto = true;
                        break;
                    case '(':
                        $goto = false;
                        $open_round++;
                        break;
                    case ')':
                        $goto = false;
                        $open_round--;
                        break;
                    case '?':
                        $goto = false;
                        $ternary_q[] = $open_round;
                        break;
                    case ':':
                        $goto = false;
                        if ($ternary_q) {
                            if ($open_round === end($ternary_q)) {
                                array_pop($ternary_q);
                            } else {
                                // this is a label
                                $hint = '';
                                $add_hint = false;
                            }
                        } else {
                            // these are labels
                            $hint = '';
                            $add_hint = false;
                        }
                        break;
                    case T_WHITESPACE:
                    case T_COMMENT:
                    case T_DOC_COMMENT:
                        $add_hint = false;
                        break;
                    default:
                        $goto = false;
                        break;
                }

                if ($add_hint && $hint !== '') {
                    $this->addHint($hint);
                    $hint = '';
                }
            }

        } while ($index < $count);

        if ($use_hints && $hint !== '') {
            $this->addHint($hint);
        }

        return $code;
    }

    /**
     * @param int $index
     * @param int $end
     * @return string
     */
    private function between(int $index, int $end): string
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
    private function walk(int $index, array $token_types, bool $match = false, int $maxLine = PHP_INT_MAX): int
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
     * @param string $hint
     * @return bool
     */
    private function addHint(string $hint): bool
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

    private function checkSpecialToken(int $index): void
    {
        if ($this->thisRef && $this->scopeRef) {
            // we already have these
            return;
        }

        $check = false;
        $checkThis = false;
        $isStatic = false;
        $isParent = false;

        $token = $this->tokens[$index];

        if ($token[0] === T_STATIC) {
            $check = $isStatic = true;
        } elseif ($token[0] === T_VARIABLE) {
            $check = $checkThis = strcasecmp($token[1], '$this') === 0;
        } elseif ($token[0] === T_STRING) {
            if (strcasecmp($token[1], 'self') === 0) {
                $check = true;
            } elseif (strcasecmp($token[1], 'parent') === 0) {
                $check = $isParent = true;
                $checkThis = !$this->isStatic;
            }
        }

        if (!$check) {
            // nothing to check
            return;
        }

        if ($checkThis) {
            if ($this->thisRef) {
                if ($isParent) {
                    $this->scopeRef = true;
                }
                // already has $this
                return;
            }
        } elseif ($this->scopeRef) {
            // already has scope
            return;
        }

        foreach ($this->anonymous as $anonymous) {
            if ($index >= $anonymous[0] && $index <= $anonymous[1]) {
                // this token is inside anonymous class body, ignore it
                return;
            }
        }

        if ($checkThis) {
            $this->thisRef = true;
            if ($isParent) {
                $this->scopeRef = true;
            }
            return;
        }

        if ($isStatic) {
            while ($index < $this->count) {
                switch ($this->tokens[++$index][0]) {
                    case T_WHITESPACE:
                    case T_COMMENT:
                    case T_DOC_COMMENT:
                        continue 2;
                    case T_VARIABLE:
                        // static variable
                        return;
                }
                break;
            }
        }

        $this->scopeRef = true;
        if ($isParent) {
            $this->thisRef = true;
        }
    }

    /**
     * @param array $namespaces
     * @param int $startLine
     * @return array|null
     */
    private static function findNamespaceAliases(array $namespaces, int $startLine): ?array
    {
        foreach ($namespaces as $info) {
            if ($startLine >= $info['start'] && $startLine <= $info['end']) {
                return $info;
            }
        }

        return null;
    }

    /**
     * @param string $prefix
     * @param array|null $items
     * @return string
     */
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

    private const FORMAT_IMPORTS_MAP = [
        'class' => 'use ',
        'func' => 'use function ',
        'const' => 'use const ',
    ];

    /**
     * @param array $alias
     * @param array $hints
     * @param string $ns
     * @return string
     */
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

    /**
     * @param ReflectionFunction $reflector
     * @return ClosureInfo|null Returns null if not a real closure (a function, from callable)
     */
    public static function parse(ReflectionFunction $reflector): ?ClosureInfo
    {
        // Check if a valid closure
        if (!$reflector->isClosure() || $reflector->isInternal() || !str_starts_with($reflector->getShortName(), '{closure')) {
            return null;
        }

        // Get file name
        $file = $reflector->getFileName();

        // Check if file name is present
        if (!$file) {
            return null;
        }

        // Try already deserialized
        // closure://...
        if ($fromStream = ClosureStream::info($file)) {
            return $fromStream;
        }

        // Get file key
        $fileKey = md5($file);

        // Get line bounds
        $startLine = $reflector->getStartLine();
        $endLine = $reflector->getEndLine();

        // compute top-level cache key
        $cacheKey = "{$fileKey}/{$startLine}/{$endLine}";

        // check cache
        if (array_key_exists($cacheKey, self::$globalInfoCache)) {
            return self::$globalInfoCache[$cacheKey];
        }

        // check file cache
        if (!array_key_exists($fileKey, self::$globalFileCache)) {
            self::$globalFileCache[$fileKey] = TokenizedFileInfo::getInfo($file);
        }

        $fileInfo = self::$globalFileCache[$fileKey];

        if ($fileInfo === null) {
            return null;
        }

        $ns = '';
        $aliases = null;
        if ($fileInfo['namespaces']) {
            if ($info = self::findNamespaceAliases($fileInfo['namespaces'], $startLine)) {
                $ns = trim($info['ns'] ?? '', '\\');
                $aliases = $info['use'] ?? null;
            }
        }

        // create parser
        $parser = new self($reflector, $fileInfo['tokens'], $ns, $aliases, $fileInfo['anonymous']);

        // cache result
        return self::$globalInfoCache[$cacheKey] = $parser->info();
    }

    public static function init(): void
    {
        if (self::$BUILTIN_TYPES) {
            // already initialized
            return;
        }

        self::$BUILTIN_TYPES = self::getBuiltInTypes();
    }

    /**
     * @return string[]
     */
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
}