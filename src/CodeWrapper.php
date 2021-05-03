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

/**
 * @internal
 */
final class CodeWrapper
{
    /**
     * @var string
     */
    private string $value;

    /**
     * @var string|null
     */
    private ?string $key = null;

    /**
     * CodeWrapper constructor.
     * @param string $code
     */
    public function __construct(string $code)
    {
        $this->value = $code;
    }

    /**
     * @return string
     */
    public function key(): string
    {
        if ($this->key === null) {
            $this->key = md5($this->value);
        }

        return $this->key;
    }

    /**
     * @return string
     */
    public function value(): string
    {
        return $this->value;
    }

    public function __serialize(): array
    {
        return ['value' => $this->value];
    }

    public function __unserialize(array $data): void
    {
        $this->value = $data['value'];
    }

    public function __toString(): string
    {
        return $this->value;
    }
}