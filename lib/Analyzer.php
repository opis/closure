<?php
/* ===========================================================================
 * Opis Project
 * http://opis.io
 * ===========================================================================
 * Copyright (c) 2014-2016 Opis Project
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure;

use Closure;
use SuperClosure\Analyzer\ClosureAnalyzer;

class Analyzer extends ClosureAnalyzer
{
    /**
     * Analyzer a given closure.
     *
     * @param Closure $closure
     *
     * @return array
     */
    public function analyze(Closure $closure)
    {
        $reflection = new ReflectionClosure($closure);
        $scope = $reflection->getClosureScopeClass();

        $data = [
            'reflection' => $reflection,
            'code'       => $reflection->getCode(),
            'hasThis'    => strpos($reflection->getCode(), '$this') !== false,
            'context'    => $reflection->getUseVariables(),
            'hasRefs'    => false,
            'binding'    => $reflection->getClosureThis(),
            'scope'      => $scope ? $scope->getName() : null,
            'isStatic'   => $reflection->isStatic(),
        ];

        return $data;
    }

    /**
     * @param array $data
     * @return mixed
     */
    protected function determineCode(array &$data)
    {

    }

    /**
     * @param array $data
     * @return mixed
     */
    protected function determineContext(array &$data)
    {

    }

}
