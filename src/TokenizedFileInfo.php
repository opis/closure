<?php

namespace Opis\Closure;

/**
 * @internal
 */
final class TokenizedFileInfo
{
    /**
     * @var array File tokens
     */
    private array $tokens;

    /**
     * @var int Total number of tokens
     */
    private int $count;

    /**
     * @var int Current token index
     */
    private int $index = 0;

    /**
     * @var int Last known line
     */
    private int $line = 0;

    /**
     * @var string|null Current namespace
     */
    private ?string $currentNamespace = null;

    /**
     * @var bool True if inside trait
     */
    private bool $insideTrait = false;

    /**
     * @var int Nested anonymous classes
     */
    private int $insideAnonymousClass = 0;

    /**
     * @var int Number of open brackets
     */
    private int $openBrackets = 0;

    /**
     * @var array Aliased structures
     */
    private array $use = [];

    /**
     * @var string Directory name
     */
    private string $dirName;

    /**
     * @var string File name
     */
    private string $fileName;

    /**
     * @var string|null Structure name class/trait/interface
     */
    private ?string $structureName = null;

    /**
     * Collected info
     * @var array
     */
    private array $collectedInfo = [];

    /**
     * Anonymous bounds
     * @var array
     */
    private array $anonymous = [];

    /**
     * @var string|null #trackme prefix
     */
    private ?string $trackPrefix = null;

    /**
     * @param string $file
     */
    private function __construct(string $file)
    {
        $this->tokens = token_get_all(file_get_contents($file), TOKEN_PARSE);
        $this->count = count($this->tokens);

        $this->fileName = var_export($file, true) ?? "''";
        $this->dirName = var_export(dirname($file), true) ?? "''";
    }

    /**
     * @return array Get info
     */
    private function info(): array
    {
        $this->handleGlobal();
        return [
            'tokens' => $this->tokens,
            'namespaces' => $this->collectedInfo,
            'anonymous' => $this->anonymous,
        ];
    }

    /**
     * Handles global code
     */
    private function handleGlobal(): void
    {
        foreach ($this->untilMarker(null) as $token) {
            switch ($token[0]) {
                case T_NAMESPACE:
                    $this->handleNamespace();
                    // Check if a new ns started
                    if ($this->index < $this->count && $this->tokens[$this->index][0] === T_NAMESPACE) {
                        $this->index--;
                    }
                    break;
                case T_USE:
                    $this->handleUseImports();
                    break;
                /** @noinspection PhpMissingBreakStatementInspection */
                case T_TRAIT:
                    $this->insideTrait = true;
                case T_INTERFACE:
                case T_CLASS:
                case T_ENUM:
                    $this->handleClass();
                    $this->insideTrait = false;
                    break;
                default:
                    if (is_array($token)) {
                        $this->handleGenericToken();
                    }
                    break;
            }
        }

        if ($this->currentNamespace || $this->use) {
            $this->collectedInfo[] = [
                'ns' => $this->currentNamespace ?? '\\',
                'use' => $this->use,
                'start' => 0,
                'end' => $this->line,
            ];
        }

        $this->use = [];
        $this->currentNamespace = null;
    }

    /**
     * Handles namespaced code
     */
    private function handleNamespace(): void
    {
        $line = $this->line;

        // Skip T_NAMESPACE
        $this->index++;

        $this->currentNamespace = '\\' . $this->readIdentifier();
        $this->use = [];

        if ($this->tokens[$this->index] === '{') {
            $tokens = $this->balanceCurly();
        } else {
            $tokens = $this->untilMarker(T_NAMESPACE, true);
        }

        foreach ($tokens as $token) {
            switch ($token[0]) {
                case T_USE:
                    $this->handleUseImports();
                    break;
                /** @noinspection PhpMissingBreakStatementInspection */
                case T_TRAIT:
                    $this->insideTrait = true;
                case T_CLASS:
                case T_INTERFACE:
                case T_ENUM:
                    $this->handleClass();
                    $this->insideTrait = false;
                    break;
                default:
                    if (is_array($token)) {
                        $this->handleGenericToken();
                    }
                    break;
            }
        }

        // Add info

        $this->collectedInfo[] = [
            'ns' => $this->currentNamespace,
            'use' => $this->use,
            'start' => $line,
            'end' => $this->line,
        ];

        // Remove ns
        $this->currentNamespace = null;
        // Remove use
        $this->use = [];
    }

    /**
     * Handles imports and aliases
     */
    private function handleUseImports(): void
    {
        // Skip T_USE
        $this->index++;

        // Skip whitespace
        $this->skipWhitespaceAndComments();

        $type = 'class';

        switch ($this->tokens[$this->index][0]) {
            case T_FUNCTION:
                $type = 'func';
                $this->index++;
                break;
            case T_CONST:
                $type = 'const';
                $this->index++;
                break;
            case '(':
                // use() for closure
                return;
        }

        if (!isset($this->use[$type])) {
            $this->use[$type] = [];
        }

        foreach ($this->readUseAlias() as $alias => $ns) {
            if ($ns[0] !== '\\') {
                $ns = '\\' . $ns;
            }

            $this->use[$type][strtolower($alias)] = $ns;
        }
    }

    /**
     * Reads import statements
     * @param string|null $prefix
     * @return iterable
     */
    private function readUseAlias(?string $prefix = null): iterable
    {
        if ($prefix !== null && $prefix !== '') {
            if ($prefix[-1] !== '\\') {
                $prefix .= '\\';
            }
        }

        do {
            $name = $this->readIdentifier();
            switch ($this->tokens[$this->index][0]) {
                case T_AS:
                    $this->index++;
                    yield $this->readIdentifier() => $prefix . $name;
                    if ($this->tokens[$this->index][0] === ',') {
                        $this->index++;
                        continue 2;
                    }
                    // stop
                    break 2;
                case ',':
                    $alias = explode('\\', $name);
                    $alias = array_pop($alias);
                    yield $alias => $prefix . $name;
                    unset($alias);
                    // read next
                    $this->index++;
                    continue 2;
                case '{':
                    $this->index++;
                    yield from $this->readUseAlias($name);
                    break;
                case ';':
                    /** @noinspection PhpMissingBreakStatementInspection */
                case '}':
                    if ($name !== '') {
                        $alias = explode('\\', $name);
                        $alias = array_pop($alias);
                        yield $alias => $prefix . $name;
                        unset($alias);
                    }
                    $this->index++;
                default:
                    break 2;
            }
        } while (true);
    }

    /**
     * Handles code inside class, interface or trait
     */
    private function handleClass(): void
    {
        // Skip T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM
        $this->index++;

        $name = $this->readIdentifier();

        if ($name === '') {
            $this->handleAnonymousClass();
            return;
        }

        if ($this->currentNamespace) {
            $this->structureName = var_export(substr($this->currentNamespace, 1) . '\\' . $name, true);
        } else {
            $this->structureName = var_export($name, true);
        }

        foreach ($this->balanceCurly() as $token) {
            if (is_array($token)) {
                if ($token[0] === T_CLASS) {
                    // We are already inside a class,
                    // so it must be anonymous
                    // Skip T_CLASS
                    $this->index++;
                    $this->handleAnonymousClass();
                } else {
                    $this->handleGenericToken();
                }
            }
        }

        $this->structureName = null;
    }

    /**
     * Handles code in anonymous class
     */
    private function handleAnonymousClass(): void
    {
        // Skip whitespace and comments
        $this->skipWhitespaceAndComments();

        // Check if the constructor is invoked
        if ($this->tokens[$this->index] === '(') {
            $open = 1;

            // Skip (
            $this->index++;

            // We have to balance

            do {
                $token = $this->tokens[$this->index];

                switch ($token[0]) {
                    case '(':
                        $open++;
                        break;
                    case ')':
                        $open--;
                        break;
                    case T_CLASS:
                        // This can only be anonymous
                        // Skip T_CLASS
                        $this->index++;
                        $this->handleAnonymousClass();
                        $this->index--;
                        break;
                    case T_CURLY_OPEN:
                    case T_DOLLAR_OPEN_CURLY_BRACES:
                    case '{':
                        $this->openBrackets++;
                        break;
                    case '}':
                        $this->openBrackets--;
                        break;
                    case T_COMMENT:
                        $this->handleTrackComment();
                        break;
                    case T_WHITESPACE:
                    case T_DOC_COMMENT:
                    case T_INLINE_HTML:
                    case T_VAR:
                        // nothing
                        break;
                    default:
                        if (is_array($token)) {
                            $this->handleGenericToken();
                        }
                        break;
                }

                $this->index++;
            } while ($open > 0 && $this->index < $this->count);
        }

        // Now we can check body
        $start = $this->index;

        if ($this->tokens[$start] !== "{") {
            $start++;
        }

        $this->insideAnonymousClass++;
        foreach ($this->balanceCurly() as $token) {
            if (is_array($token)) {
                if ($token[0] === T_CLASS) {
                    // We are already inside an anonymous class
                    // Skip T_CLASS
                    $this->index++;
                    $this->handleAnonymousClass();
                } else {
                    $this->handleGenericToken();
                }
            }
        }
        $this->insideAnonymousClass--;

        if ($start !== $this->index - 1) {
            $this->anonymous[] = [$start, $this->index - 1];
        }
    }

    /**
     * Handles generic tokens, like magic constants
     */
    private function handleGenericToken(): void
    {
        $token = &$this->tokens[$this->index];
        $this->line = $token[2];

        switch ($token[0]) {
            case T_LINE:
                $token[0] = T_LNUMBER;
                $token[1] = (string)$this->line;
                break;
            case T_FILE:
                $token[0] = T_CONSTANT_ENCAPSED_STRING;
                $token[1] = $this->fileName;
                break;
            case T_DIR:
                $token[0] = T_CONSTANT_ENCAPSED_STRING;
                $token[1] = $this->dirName;
                break;
            case T_TRAIT_C:
                $token[0] = T_CONSTANT_ENCAPSED_STRING;
                $token[1] = $this->insideTrait ? ($this->structureName ?? "''") : "''";
                break;
            case T_CLASS_C:
                if (!$this->insideAnonymousClass && !$this->insideTrait) {
                    $token[0] = T_CONSTANT_ENCAPSED_STRING;
                    $token[1] = $this->structureName ?? "''";
                }
                break;
        }
    }

    /**
     * Replaces the #trackme comment with info
     */
    private function handleTrackComment(): void
    {
        $token = &$this->tokens[$this->index];

        if ($token[1][0] === '#' && str_starts_with($token[1], '#trackme')) {
            if ($this->trackPrefix === null) {
                $this->trackPrefix = ' - generated at ' . date(DATE_W3C) . ', file ' . $this->fileName . ', line ';
            }
            // Only append line number to track prefix
            $token[1] = '// ' . trim(substr($token[1], 8)) . $this->trackPrefix . $token[2];
        }
    }

    /**
     * Utility to read an identifier (class name, namespace, ...)
     * @return string
     */
    private function readIdentifier(): string
    {
        $name = "";

        while ($this->index < $this->count) {
            $token = $this->tokens[$this->index];
            switch ($token[0]) {
                case T_STRING:
                case T_NS_SEPARATOR:
                case T_NAME_QUALIFIED:
                case T_NAME_FULLY_QUALIFIED:
                    $name .= $token[1];
                    $this->index++;
                    $this->line = $token[2];
                    continue 2;
                /** @noinspection PhpMissingBreakStatementInspection */
                case T_COMMENT:
                    $this->handleTrackComment();
                case T_WHITESPACE:
                case T_DOC_COMMENT:
                case T_INLINE_HTML:
                case T_CLOSE_TAG:
                case T_OPEN_TAG:
                    $this->index++;
                    $this->line = $token[2];
                    continue 2;
            }
            break;
        }

        return $name;
    }

    /**
     * Yields tokens until the marker is found
     * @param $marker
     * @param bool $noAdvance
     * @return iterable
     */
    private function untilMarker($marker, bool $noAdvance = false): iterable
    {
        while ($this->index < $this->count) {
            $token = $this->tokens[$this->index];
            if (is_array($token)) {
                $this->line = $token[2];
                switch ($token[0]) {
                    case T_CURLY_OPEN:
                    case T_DOLLAR_OPEN_CURLY_BRACES:
                        $this->openBrackets++;
                        break;
                    // ignore whitespaces and comments
                    /** @noinspection PhpMissingBreakStatementInspection */
                    case T_COMMENT:
                        $this->handleTrackComment();
                    case T_DOC_COMMENT:
                    case T_WHITESPACE:
                    case T_INLINE_HTML:
                    case T_CLOSE_TAG:
                    case T_OPEN_TAG:
                        $this->index++;
                        continue 2;
                }
            } else {
                if ($token === '{') {
                    $this->openBrackets++;
                } elseif ($token === '}') {
                    $this->openBrackets--;
                }
            }

            yield $token;

            $this->index++;

            if ($token[0] === $marker) {
                if ($noAdvance) {
                    $this->index--;
                }
                return;
            }
        }
    }

    /**
     * Yields until the matching curly bracket is closed
     * @return iterable
     */
    private function balanceCurly(): iterable
    {
        $marker = $this->openBrackets;

        do {
            yield from $this->untilMarker('}');
        } while ($this->openBrackets > $marker);
    }

    /**
     * Skips whitespace and comments
     */
    private function skipWhitespaceAndComments(): void
    {
        while ($this->index < $this->count) {
            $token = $this->tokens[$this->index];
            switch ($token[0]) {
                /** @noinspection PhpMissingBreakStatementInspection */
                case T_COMMENT:
                    $this->handleTrackComment();
                case T_WHITESPACE:
                case T_DOC_COMMENT:
                case T_INLINE_HTML:
                case T_CLOSE_TAG:
                case T_OPEN_TAG:
                    $this->index++;
                    $this->line = $token[2];
                    continue 2;
            }
            return;
        }
    }

    /**
     * Get info
     * @param string $file
     * @return array
     */
    public static function getInfo(string $file): array
    {
        return (new self($file))->info();
    }
}