<?php
/* ===========================================================================
 * Copyright 2020 Zindex Software
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

use ReflectionFunction;

/**
 * @internal
 */
final class ReflectionFunctionInfo
{
    /**
     * @var array Transformed file tokens cache
     */
    private static array $globalFileCache = [];

    /**
     * @var array Closure tokens cache
     */
    private static array $globalInfoCache = [];

    /**
     * List of builtin php types
     */
    private const BUILTIN = \PHP_MAJOR_VERSION === 8
        ? [
            'bool', 'int', 'float', 'string', 'array',
            'object', 'iterable', 'callable', 'void', 'mixed',
            'self', 'parent', 'static',
            'false', 'null',
        ]
        : [
            'bool', 'int', 'float', 'string', 'array',
            'object', 'iterable', 'callable', 'void',
            'self', 'parent', 'static',
        ];

    private ReflectionFunction $reflector;

    private array $tokens;
    private int $count;
    private int $index = -1;
    private ?array $aliases;

    private bool $isShort = false;
    private bool $isStatic = false;
    private array $use = [];
    private array $hints = [];

    private function __construct(ReflectionFunction $reflector, array $tokens, ?array $aliases)
    {
        $this->reflector = $reflector;
        $this->tokens = $tokens;
        $this->count = count($tokens);
        $this->aliases = $aliases;
    }

    private function process(): ?array
    {
        $this->functionInit();
        if ($this->index === -1) {
            return null;
        }

        $code = $this->code() . ";";

        if ($this->isStatic) {
            $code = 'return static ' . $code;
        } else {
            $code = 'return ' . $code;
        }

        $code = $this->imports() . $code;

        return [
            'static' => $this->isStatic,
            'code' => new CodeWrapper($code),
            'short' => $this->isShort,
            'use' => $this->use ?: null,
        ];
    }

    /**
     * Initialize index
     */
    private function functionInit(): void
    {
        $startLine = $this->reflector->getStartLine();

        $index = -1;

        $tokens = $this->tokens;

        foreach ($tokens as $key => $token) {
            if (is_array($token) && $token[2] >= $startLine) {
                $index = $key;
                break;
            }
        }

        if ($index === -1) {
            return;
        }

        // Search for T_FUNCTION / T_FN

        $count = $this->count;
        $endLine = $this->reflector->getEndLine();

        do {
            switch ($tokens[$index][0]) {
                case T_FN:
                    $this->isShort = true;
                    $this->index = $index;
                    break 2;
                case T_FUNCTION:
                    for ($i = $index + 1; $index < $count; $i++) {
                        switch ($tokens[$i][0]) {
                            case '(':
                                $this->index = $index;
                                break 4;
                            case T_WHITESPACE:
                            case T_COMMENT:
                            case T_DOC_COMMENT:
                            case '&':
                                continue 2;
                        }

                        $index = $i;
                    }
                    break;
                default:
                    if (is_array($tokens[$index]) && $tokens[$index][2] > $endLine) {
                        break 2;
                    }
                    break;
            }
        } while (++$index < $count);

        while (--$index >= 0) {
            switch ($tokens[$index][0]) {
                case T_WHITESPACE:
                case T_COMMENT:
                case T_DOC_COMMENT:
                    continue 2;
                case T_STATIC:
                    $this->isStatic = true;
                    return;
            }

            return;
        }
    }

    /**
     * @returns string
     */
    private function imports(): string
    {
        $code = "<?php\n";

        $ns = $this->reflector->getNamespaceName();

        if ($ns) {
            $code .= "namespace {$ns};\n";
        }

        if (!$this->aliases || !$this->hints) {
            return $code;
        }


        $code .= self::formatImports($this->aliases, $this->hints, $ns);


        return $code;
    }

    /**
     * @return string
     */
    private function code(): string
    {
        $tokens = $this->tokens;
        $count = $this->count;
        $index = &$this->index;

        $code = '';

        // Function start

        do {
            $token = $tokens[$index];
            if ($token === '(') {
                break;
            }
            $code .= is_array($token) ? $token[1] : $token;
        } while (++$index < $count);

        // Function args
        if ($this->reflector->getNumberOfParameters() > 0) {
            $code .= $this->balance( '(', ')');
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
            $code .= $this->balanceExpression();
        } else {
            $code .= $this->balance(['{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], '}', $has_ret_type ? 1 : 0);
        }

        return $code;
    }

    /**
     * @return string
     */
    private function balanceExpression(): string
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

        do {
            $add_hint = true;
            $token = $tokens[$index++];

            switch ($token[0]) {
                case T_NS_SEPARATOR:
                case T_STRING:
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

                // Delimiters
                case ',':
                case ';':
                    if ($open_curly <= 0 && $open_round === 0 && $open_square === 0) {
                        break 2;
                    }
                    break;
            }

            $code .= is_array($token) ? $token[1] : $token;

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

        do {
            $token = $tokens[$index++];

            /*
            if (is_array($token)) {
                if ($token[0] === T_CLASS_CURRENT_C) { // custom constant
                    $code .= $this->currentClass;
                } else {
                    $code .= $token[1];
                }
            } else {
                $code .= $token;
            }*/

            $code .= is_array($token) ? $token[1] : $token;

            if ($is_array_start ? in_array($token[0], $start, true) : $token[0] === $start) {
                $open++;
            } elseif ($token[0] === $end) {
                if (--$open === 0) {
                    break;
                }
            }

            switch ($token[0]) {
                case T_STRING:
                case T_NS_SEPARATOR:
                    if ($use_hints) {
                        $hint .= $token[1];
                    }
                    break;
                case T_WHITESPACE:
                case T_COMMENT:
                case T_DOC_COMMENT:
                    // ignore whitespace and comments
                    break;
                default:
                    if ($use_hints && $hint !== '') {
                        $this->addHint($hint);
                        $hint = '';
                    }
                    break;
            }

        } while ($index < $count);

        if ($use_hints && $hint !== '') {
            $this->addHint($hint);
        }

        return $code;
    }

    /**
     * @param string $hint
     * @return bool
     */
    private function addHint(string $hint): bool
    {
        if (!$hint || strpos($hint, '\\') !== false) {
            return false;
        }

        $key = strtolower($hint);

        if (isset($this->hints[$key]) || in_array($key, self::BUILTIN)) {
            return false;
        }

        $this->hints[$key] = $hint;

        return true;
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
                return $info['use'] ?? null;
            }
        }

        return null;
    }

    /**
     * @param string $prefix
     * @param array $items
     * @return string
     */
    private static function formatUse(string $prefix, array $items): string
    {
        foreach ($items as $key => $value) {
            if (strcasecmp('\\' . $value[1], substr($value[0], - strlen($value[1]) - 1)) === 0) {
                $items[$key] = trim($value[0], '\\');
            } else {
                $items[$key] = trim($value[0], '\\') . ' as ' . $value[1];
            }
        }

        if (!$items) {
            return '';
        }

        return $prefix . implode(",\n" . str_repeat(' ', strlen($prefix)), $items) . ";\n";
    }

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
            foreach ($alias as $type => $values) {
                if (!isset($values[$hint])) {
                    continue;
                }

                if (strcasecmp($ns . '\\' . $hint, $values[$hint]) === 0) {
                    // Skip redundant import
                    continue;
                }

                if (!isset($use[$type])) {
                    $use[$type] = [];
                }

                $use[$type][] = [$values[$hint], $hintValue];
            }
        }

        if (!$use) {
            return '';
        }

        $code = '';

        if (isset($use['class'])) {
            $code .= self::formatUse('use ', $use['class']);
        }
        if (isset($use['func'])) {
            $code .= self::formatUse('use function ', $use['func']);
        }
        if (isset($use['const'])) {
            $code .= self::formatUse('use const ', $use['const']);
        }

        return $code;
    }

    /**
     * @param ReflectionFunction $reflector
     * @return array|null Returns null if not a real closure (a function, from callable)
     */
    public static function getInfo(ReflectionFunction $reflector): ?array
    {
        // Check if a valid closure
        if (!$reflector->isClosure() || $reflector->isInternal() || $reflector->getShortName() !== '{closure}') {
            return null;
        }

        // Get file name
        $file = $reflector->getFileName();

        // Check if file name is present
        if (!$file) {
            return null;
        }

        // Get file key
        $fileKey = md5($file);

        // Get line bounds
        $startLine = $reflector->getStartLine();
        $endLine = $reflector->getEndLine();

        // Compute top-level cache key
        $cacheKey = "{$fileKey}/{$startLine}/{$endLine}";

        // Check cache
        if (array_key_exists($cacheKey, self::$globalInfoCache)) {
            return self::$globalInfoCache[$cacheKey];
        }

        // Check file cache
        if (!array_key_exists($fileKey, self::$globalFileCache)) {
            self::$globalFileCache[$fileKey] = TokenizedFileInfo::getInfo($file);
        }

        $fileInfo = self::$globalFileCache[$fileKey];

        if ($fileInfo === null) {
            return null;
        }

        $nsInfo = null;
        if ($fileInfo['namespaces']) {
            $nsInfo = self::findNamespaceAliases($fileInfo['namespaces'], $startLine);
        }

        // Cache result and return info
        return self::$globalInfoCache[$cacheKey] = (new self($reflector, $fileInfo['tokens'], $nsInfo))->process();
    }
}