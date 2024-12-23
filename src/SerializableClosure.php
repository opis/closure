<?php

namespace Opis\Closure;

use Opis\Closure\Security\SecurityException;
use Serializable, RuntimeException;
use function unserialize;

/**
 * Class used for 3.x unserialize compatibility
 * @deprecated This will be removed in 5.x
 * @internal
 */
final class SerializableClosure implements Serializable
{
    /**
     * @var array|null Unserialized data
     * use => array|null Closure variables
     * function => string Closure PHP code
     * scope => string|null Closure scope
     * this => object|null Closure bound object
     * self => string Closure uniq id
     */
    public ?array $data = null;

    private function __construct()
    {

    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     */
    public function serialize(): ?string
    {
        // do not allow serialization
        $this->throwNotAllowed();
    }

    /**
     * @inheritDoc
     */
    public function unserialize(string $data): void
    {
        if (!Serializer::$v3Compatible) {
            throw new RuntimeException("You must enable v3 compatibility for " . Serializer::class);
        }

        $security = Serializer::getSecurityProvider();

        if ($security) {
            if ($data[0] !== "@") {
                throw new SecurityException("(v3) The serialized closure is NOT signed.");
            }
            if ($data[1] !== "{") {
                // v3
                $separator = strpos($data, '.');
                if ($separator === false) {
                    throw new SecurityException("(v3) Invalid signed closure");
                }
                $data = [
                    "hash" => substr($data, 1, $separator - 1),
                    "closure" => substr($data, $separator + 1),
                ];
            } else {
                // v2
                $data = json_decode(substr($data, 1), true);
            }
            if (!is_array($data) || !$security->verify($data["hash"] ?? "", $data["closure"] ?? "")) {
                throw new SecurityException(
                    "(v3) Your serialized closure might have been modified and it's unsafe to be unserialized. " .
                    "Make sure you use the correct security provider."
                );
            }
            // the new data
            $data = $data["closure"];
        } elseif ($data[0] === "@") {
            throw new SecurityException("(v3) The serialized closure is signed, use a security provider.");
        }

        // We just save the data
        $this->data = unserialize($data);
    }

    /**
     * @return array
     * @throws RuntimeException
     */
    public function __serialize(): array
    {
        $this->throwNotAllowed();
    }

    public function __unserialize(array $data): void
    {
        // This should never happen since we prevented __serialize()
        throw new RuntimeException("Unserialization of " . self::class . " should never happen!");
    }

    private function throwNotAllowed(): void
    {
        throw new RuntimeException("Serialization of " . self::class . " (3.x compatible) is not allowed!");
    }
}
