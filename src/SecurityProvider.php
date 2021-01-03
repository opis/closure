<?php
/* ===========================================================================
 * Copyright 2018-2021 Zindex Software
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
 * @deprecated
 */
class SecurityProvider implements ISecurityProvider
{
    /** @var  string */
    protected string $secret;

    /**
     * SecurityProvider constructor.
     * @param string $secret
     */
    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * @inheritdoc
     */
    public function sign($closure)
    {
        return array(
            'closure' => $closure,
            'hash' => base64_encode(hash_hmac('sha256', $closure, $this->secret, true)),
        );
    }

    /**
     * @inheritdoc
     */
    public function verify(array $data)
    {
        return base64_encode(hash_hmac('sha256', $data['closure'], $this->secret, true)) === $data['hash'];
    }
}