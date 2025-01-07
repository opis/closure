<?php

namespace Opis\Closure;

/**
 * @internal
 */
final class AnonymousClassParser extends AbstractParser
{
    private int $last = -1;

    protected function __construct(
        private ReflectionClass $reflector,
        string                  $ns,
        ?array                  $aliases,
        array                   $tokens,
        array                   $anonymous
    )
    {
        parent::__construct($ns, $aliases, $tokens, $anonymous);
    }

    protected function getBody(): string
    {
        $index = $this->index;
        $tokens = $this->tokens;

        for ($start_index = $index - 1; $start_index >= 0; $start_index--) {
            if ($tokens[$start_index][0] === T_NEW) {
                break;
            }
        }

        $start_index++;
        // skip whitespace after T_NEW
        while ($tokens[$start_index][0] === T_WHITESPACE) {
            $start_index++;
        }

        $code = $this->between($start_index, $index - 1);
        if ($code) {
            // put class on a new line
            $code .= "\n";
        }
        $code .= "class " . AnonymousClassInfo::PLACEHOLDER;

        // skip T_CLASS
        $index++;
        if ($tokens[$index][0] !== T_WHITESPACE) {
            // add a space if necessary
            $code .= " ";
        }

        $next_index = $this->walk($index, ['(', '{'], true, $this->reflector->getEndLine());

        $code .= $this->between($index, $next_index - 1);

        $endsInWs = $tokens[$next_index - 1][0] === T_WHITESPACE;
        $last = $this->last;

        if ($tokens[$next_index] === '(') {
            $open = 1;
            while (++$next_index < $last) {
                switch ($tokens[$next_index]) {
                    case '(':
                        $open++;
                        break;
                    case ')':
                        if (!--$open) {
                            $next_index++;
                            break 2;
                        }
                        break;
                }
            }
            // skip ws
            if ($endsInWs) {
                while ($tokens[$next_index][0] === T_WHITESPACE) {
                    $next_index++;
                }
            }
        }

        $code .= $this->between($next_index, $last);

        return $code;
    }

    public function getInfo(): ?AnonymousClassInfo
    {
        if (!$this->findClassIndex()) {
            return null;
        }

        // we have to load body first
        $body = $this->getBody();
        // then we have can get the header
        $header = $this->getHeader();

        return new AnonymousClassInfo($header, $body, $this->ns);
    }

    private function findClassIndex(): bool
    {
        if (!$this->anonymous) {
            return false;
        }

        // line of T_CLASS
        $startLine = $this->reflector->getStartLine();
        $tokens = $this->tokens;

        $last = -1;
        foreach ($this->anonymous as $range) {
            // search for T_CLASS
            $index = -1;
            $i = $range[0];
            while ($i > $last) {
                if ($tokens[$i][0] === T_CLASS) {
                    $index = $i;
                    break;
                }
                $i--;
            }

            if ($index < 0) {
                $last = $range[0];
                continue;
            }

            // we have a T_CLASS, we must check the line
            if ($tokens[$index][2] >= $startLine) {
                // TODO: we can extract the position of the class in the same line using the number after $
                // SomeClassName@anonymous file:7$0
                $this->index = $index;
                $this->last = $range[1];
                return true;
            }
        }

        return false;
    }

    /**
     * @param ReflectionClass $reflector
     * @return AnonymousClassInfo|null
     */
    public static function parse($reflector): ?AnonymousClassInfo
    {
        if (is_string($reflector)) {
            $reflector = ReflectionClass::get($reflector);
        }

        if ($reflector->isInternal() || !$reflector->isAnonymousLike()) {
            return null;
        }

        return self::resolve($reflector, AnonymousClassInfo::name());
    }

    /**
     * @param ReflectionClass $reflector
     * @param string $ns
     * @param array $fileInfo
     * @param array|null $aliases
     * @return static
     */
    protected static function create($reflector, string $ns, array $fileInfo, ?array $aliases): static
    {
        return new self($reflector, $ns, $aliases, $fileInfo["tokens"], $fileInfo["anonymous"]);
    }
}