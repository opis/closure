<?php

namespace Opis\Closure\Test\PHP84;

use Opis\Closure\ReflectionClass;
use Opis\Closure\Test\SerializeTestCase;

class SerializeTest extends SerializeTestCase
{
    public function testHooks()
    {
        $v = new class() {
            private string $privateValue = "secret";

            // this should not be serialized
            public float $computedTime {
                get => microtime(true);
            }

            public string $value = "def" {
                get => $this->value . "-from-getter";
                set(string $value) {
                    $this->value = $value;
                }
            }

            public function getSecret(): string
            {
                return $this->privateValue;
            }
        };

        $v->value = "my-value";
        $this->assertEquals("my-value-from-getter", $v->value);

        // serialization

        $u = $this->process($v);
        $this->assertEquals("my-value-from-getter", $u->value);
        $this->assertEquals("secret", $u->getSecret());

        // test virtual prop
        $now = microtime(true);
        usleep(1);
        // the computed time should be in realtime (so > $now)
        $this->assertGreaterThan($now, $u->computedTime);
    }

    public function testHooksWithMagicSerialize()
    {
        $v = new class() {
            public string $value = "def" {
                get => $this->value . "-from-getter";
                set(string $value) {
                    $this->value = $value;
                }
            }

            public function __serialize(): array
            {
                // this calls hook
                return [$this->value];
            }

            public function __unserialize(array $data): void
            {
                // this calls hook
                [$this->value] = $data;
            }
        };

        $v->value = "my-value";
        $this->assertEquals("my-value-from-getter", $v->value);

        // serialization
        $u = $this->process($v);
        $this->assertEquals("my-value-from-getter-from-getter", $u->value);
    }

    public function testHooksWithMagicSerializeAndCustomRawPropertiesResolver()
    {
        $v = new class() {
            public function __construct()
            {
                $this->pub = "1.pub";
                $this->prot = "2.prot";
                $this->priv = "3.priv";
            }

            public string $pub = "pub" {
                get => $this->pub . "-from-getter";
                set(string $value) {
                    $this->pub = $value;
                }
            }

            protected string $prot = "prot" {
                get => $this->prot . "-from-getter";
                set(string $value) {
                    $this->prot = $value;
                }
            }

            protected string $priv = "priv" {
                get => $this->priv . "-from-getter";
                set(string $value) {
                    $this->priv = $value;
                }
            }

            public string $computed {
                get => implode(", ", [$this->pub, $this->prot, $this->priv]);
            }

            public function __serialize(): array
            {
                // this does NOT call hooks
                return ReflectionClass::getRawProperties($this, ["pub", "prot", "priv"]);
            }

            public function __unserialize(array $data): void
            {
                // this calls hook
                $this->pub = $data["pub"];
                $this->prot = $data["prot"];
                $this->priv = $data["priv"];
            }
        };

        $this->assertEquals("1.pub-from-getter, 2.prot-from-getter, 3.priv-from-getter", $v->computed);

        // serialization
        $u = $this->process($v);
        $this->assertEquals("1.pub-from-getter, 2.prot-from-getter, 3.priv-from-getter", $u->computed);
    }
}