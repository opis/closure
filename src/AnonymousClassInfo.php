<?php

namespace Opis\Closure;

#[Attribute\PreventBoxing]
final class AnonymousClassInfo extends AbstractInfo
{
    public const PLACEHOLDER = "\x07opis\x06anonymous＠class\x05name\x07";

    /**
     * @var string Original class namespace
     */
    private string $ns;

    /**
     * @var bool True if class was loaded
     */
    private bool $loaded = false;

    public function __construct(string $header, string $body, string $ns = '')
    {
        parent::__construct($header, $body);
        $this->ns = trim($ns, '\\');
    }

    public function __serialize(): array
    {
        $data = parent::__serialize();
        if ($this->ns) {
            $data["ns"] = $this->ns;
        }
        return $data;
    }

    public function __unserialize(array $data): void
    {
        $this->ns = $data["ns"] ?? "";
        parent::__unserialize($data);
    }

    /**
     * Loads the class if not already loaded
     * @return string Name of the loaded class
     */
    public function loadClass(): string
    {
        $class = $this->fullClassName();

        if (!$this->loaded) {
            if (!class_exists($class, false)) {
                // include the class
                CodeStream::include($this);
            }
            $this->loaded = true;
        }

        return $class;
    }

    public function fullClassName(): string
    {
        $class = ReflectionClass::ANONYMOUS_CLASS_PREFIX . $this->key();
        return $this->ns ? "\\{$this->ns}\\{$class}" : $class;
    }

    public function getFactoryPHP(bool $phpTag = true): string
    {
        return $this->getPHP($phpTag, true);
    }

    public function getIncludePHP(bool $phpTag = true): string
    {
        return $this->getPHP($phpTag, false);
    }

    private function getPHP(bool $phpTag, bool $check): string
    {
        $code = $phpTag ? '<?php' . "\n" : "";
        if ($this->header) {
            $code .= $this->header . "\n";
        }

        // name without namespace
        $class = ReflectionClass::ANONYMOUS_CLASS_PREFIX . $this->key();

        if ($check) {
            $code .= "if (!\class_exists({$class}::class, false)):\n";
        }

        $code .= preg_replace('/' . self::PLACEHOLDER . '/', $class, $this->body, 1);

        if ($check) {
            $code .= "\nendif;";
        }

        return $code;
    }

    public static function name(): string
    {
        return "an";
    }
}
