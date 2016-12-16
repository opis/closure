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
use ReflectionFunction;

class ReflectionClosure extends ReflectionFunction
{
    protected $code;
    protected $tokens;
    protected $hashedName;
    protected $useVariables;
    protected $isStaticClosure;
    protected $isScopeRequired;
    protected $isBindingRequired;

    protected static $files = array();
    protected static $classes = array();
    protected static $functions = array();
    protected static $constants = array();
    protected static $structures = array();

    /**
     * ReflectionClosure constructor.
     * @param Closure $closure
     * @param string|null $code
     */
    public function __construct(Closure $closure, $code = null)
    {
        $this->code = $code;
        parent::__construct($closure);
    }

    /**
     * @return bool
     */
    public function isStatic()
    {
        if ($this->isStaticClosure === null) {
            $this->isStaticClosure = strtolower(substr($this->getCode(), 0, 6)) === 'static';
        }

        return $this->isStaticClosure;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        if($this->code !== null){
            return $this->code;
        }

        $fileName = $this->getFileName();
        $line = $this->getStartLine() - 1;

        $match = ClosureStream::STREAM_PROTO . '://';

        if ($line === 1 && substr($fileName, 0, strlen($match)) === $match) {
            return $this->code = substr($fileName, strlen($match));
        }

        $className = null;

        if (SerializableClosure::supportBinding()) {
            if (null !== $className = $this->getClosureScopeClass()) {
                $className = '\\' . trim($className->getName(), '\\');
            }
        }

        $php7 = '7' === "\u{37}";
        $php_types = array('string', 'int', 'bool', 'float');
        $ns = $this->getNamespaceName();
        $nsf = $ns == '' ? '' : ($ns[0] == '\\' ? $ns : '\\' . $ns);

        $_file = var_export($fileName, true);
        $_dir = var_export(dirname($fileName), true);
        $_namespace = var_export($ns, true);
        $_class = var_export(trim($className, '\\'), true);
        $_function = $ns . ($ns == '' ? '' : '\\') . '{closure}';
        $_method = ($className == '' ? '' : trim($className, '\\') . '::') . $_function;
        $_function = var_export($_function, true);
        $_method = var_export($_method, true);
        $_trait = null;

        $hasTraitSupport = defined('T_TRAIT_C');
        $tokens = $this->getTokens();
        $state = $lastState = 'start';
        $open = 0;
        $code = '';
        $buffer = $name = '';
        $new_key_word = false;
        $classes = $functions = $constants = null;
        $use = array();
        $lineAdd = 0;
        $isUsingScope = false;
        $isUsingThisObject = false;


        for($i = 0, $l = count($tokens); $i < $l; $i++) {
            $token = $tokens[$i];
            echo $state, ' => ', (is_array($token) ? $token[1] : $token), PHP_EOL;
            switch ($state) {
                case 'start':
                    if ($token[0] === T_FUNCTION || $token[0] === T_STATIC) {
                        $code .= $token[1];
                        $state = $token[0] === T_FUNCTION ? 'function' : 'static';
                    }
                    break;
                case 'static':
                    if ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_FUNCTION) {
                        $code .= $token[1];
                        if ($token[0] === T_FUNCTION) {
                            $state = 'function';
                        }
                    } else {
                        $code = '';
                        $state = 'start';
                    }
                    break;
                case 'function':
                    switch ($token[0]){
                        case T_STRING:
                            $code = '';
                            $state = 'named_function';
                            break;
                        case '(':
                            $code .= '(';
                            $state = 'closure_args';
                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;
                    }
                    break;
                case 'named_function':
                    if($token[0] === T_FUNCTION || $token[0] === T_STATIC){
                        $code = $token[1];
                        $state = $token[0] === T_FUNCTION ? 'function' : 'static';
                    }
                    break;
                case 'closure_args':
                    switch ($token[0]){
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            $buffer = $name = $token[1];
                            $new_key_word = false;
                            $state = 'name';
                            $lastState = 'closure_args';
                            if($token[0] === T_STRING){
                                if ($classes === null) {
                                    $classes = $this->getClasses();
                                }
                                if (isset($classes[$name])) {
                                    $buffer = $name = $classes[$name];
                                }
                            }
                            break;
                        case T_USE:
                            $code .= $token[1];
                            $state = 'use';
                            break;
                        case '{':
                            $code .= '{';
                            $state = 'closure';
                            $open++;
                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;
                    }
                    break;
                case 'use':
                    switch ($token[0]){
                        case T_VARIABLE:
                            $use[] = substr($token[1], 1);
                            $code .= $token[1];
                            break;
                        case '{':
                            $code .= '{';
                            $state = 'closure';
                            $open++;
                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;
                            break;
                    }
                    break;
                case 'closure':
                    switch ($token[0]){
                        case T_CURLY_OPEN:
                        case T_DOLLAR_OPEN_CURLY_BRACES:
                        case T_STRING_VARNAME:
                        case '{':
                            $code .= '{';
                            $open++;
                            break;
                        case '}':
                            $code .= '}';
                            if(--$open === 0){
                                break 3;
                            }
                            break;
                        case T_LINE:
                            $code .= $token[2] - $line + $lineAdd;
                            break;
                        case T_FILE:
                            $code .= $_file;
                            break;
                        case T_DIR:
                            $code .= $_dir;
                            break;
                        case T_NS_C:
                            $code .= $_namespace;
                            break;
                        case T_CLASS_C:
                            $code .= $_class;
                            break;
                        case T_FUNC_C:
                            $code .= $_function;
                            break;
                        case T_METHOD_C:
                            $code .= $_method;
                            break;
                        case T_COMMENT:
                            if (substr($token[1], 0, 8) === '#trackme') {
                                $timestamp = time();
                                $code .= '/**' . PHP_EOL;
                                $code .= '* Date      : ' . date(DATE_W3C, $timestamp) . PHP_EOL;
                                $code .= '* Timestamp : ' . $timestamp . PHP_EOL;
                                $code .= '* Line      : ' . ($line + 1) . PHP_EOL;
                                $code .= '* File      : ' . $_file . PHP_EOL . '*/' . PHP_EOL;
                                $lineAdd += 5;
                            } else {
                                $code .= $token[1];
                            }
                            break;
                        case T_VARIABLE:
                            if($token[1] == '$this'){
                                $isUsingThisObject = true;
                            }
                            $code .= $token[1];
                            break;
                        case T_STATIC:
                            $isUsingScope = true;
                            $code .= $token[1];
                            break;
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            $buffer = $name = $token[1];
                            $new_key_word = false;
                            $state = 'name';
                            $lastState = 'closure';
                            if($token[0] === T_STRING){
                                if ($classes === null) {
                                    $classes = $this->getClasses();
                                }
                                if (isset($classes[$name])) {
                                    $buffer = $name = $classes[$name];
                                }
                            }
                            break 2;
                        case T_NEW:
                            $buffer = $token[1];
                            $new_key_word = true;
                            $state = 'new';
                            break 2;
                        default:
                            if ($hasTraitSupport && $token[0] == T_TRAIT_C) {
                                if ($_trait === null) {
                                    $startLine = $this->getStartLine();
                                    $endLine = $this->getEndLine();
                                    $structures = $this->getStructures();

                                    $_trait = '';

                                    foreach ($structures as &$struct) {
                                        if ($struct['type'] === 'trait' &&
                                            $struct['start'] <= $startLine &&
                                            $struct['end'] >= $endLine
                                        ) {
                                            $_trait = ($ns == '' ? '' : $ns . '\\') . $struct['name'];
                                            break;
                                        }
                                    }

                                    $_trait = var_export($_trait, true);
                                }

                                $token[1] = $_trait;
                            } else {
                                $code .= is_array($token) ? $token[1] : $token;
                            }
                    }
                    break;
                case 'name':
                    switch ($token[0]){
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            $name .= $token[1];
                            $buffer .= $token[1];
                            break;
                        case T_WHITESPACE:
                            $buffer .= $token[1];
                            break;
                        case '(':
                            if($new_key_word){
                                if ($classes === null) {
                                    $classes = $this->getClasses();
                                }
                                if (isset($classes[$name])) {
                                    $name = $classes[$name];
                                }
                                if($name[0] !== '\\'){
                                    $name = $nsf . '\\' . $name;
                                }
                                $code .= $name . substr($buffer, strlen(rtrim($buffer))) . '(';
                                $state = $lastState;
                            } else {
                                if($functions === null){
                                    $functions = $this->getFunctions();
                                }
                                if(isset($functions[$name])){
                                    $name = $functions[$name];
                                }
                                $code .= $name . substr($buffer, strlen(rtrim($buffer))) . '(';
                                $state = $lastState;
                            }
                            break;
                        case T_VARIABLE:
                        case T_DOUBLE_COLON:
                            if ($classes === null) {
                                $classes = $this->getClasses();
                            }
                            if (isset($classes[$name])) {
                                $name = $classes[$name];
                            }

                            if($name['0'] === '\\'){
                                $code .= $buffer;
                            } elseif ($name == 'self' || $name === 'static'){
                                $code .= $buffer;
                                $isUsingScope = true;
                            } elseif ($php7 && in_array($name, $php_types)){
                                $code .= $buffer;
                            } else {
                                $name = $nsf . '\\' . $name;
                            }
                            $code .= $name . substr($buffer, strlen(rtrim($buffer))) . $token[1];
                            $state = $token[0] === T_DOUBLE_COLON ? 'ignore_name' : $lastState;
                            break;
                        default:
                            if($constants === null){
                                $constants = $this->getConstants();
                            }
                            if(isset($constants[$name])){
                                $name = $constants[$name];
                                $code .= $name . substr($buffer, strlen(rtrim($buffer)));
                            } else {
                                $code .= $buffer;
                            }
                            $state = $lastState;
                            $i--;//reprocess last token
                    }
                    break;
                case 'ignore_name':
                    if($token[0] === T_WHITESPACE){
                        $code .= $token[1];
                    } else {
                        $state = $lastState;
                        $i--;//reprocess
                    }
                    break;
                case 'instanceof':
                    break;
                case 'new':
                    switch ($token[0]){
                        case T_WHITESPACE:
                            $buffer .= $token[1];
                            break;
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            $code .= $buffer;
                            $buffer = $name = $token[1];
                            $state = 'name';
                            $lastState = 'closure';
                            if($token[0] === T_STRING){
                                if ($classes === null) {
                                    $classes = $this->getClasses();
                                }
                                if (isset($classes[$name])) {
                                    $buffer = $name = $classes[$name];
                                }
                            }
                            break 2;
                        default:
                            $i--;//reprocess last
                            $state = 'closure';
                            break;
                    }
                    break;
            }
        }

        $this->isBindingRequired = $isUsingThisObject;
        $this->isScopeRequired = $isUsingScope;
        $this->code = $code;
        $this->useVariables = empty($use) ? $use : array_intersect_key($this->getStaticVariables(), array_flip($use));

        return $this->code;
    }

    /**
     * @return array
     */
    public function getUseVariables()
    {
        if($this->useVariables !== null){
            return $this->useVariables;
        }

        $tokens = $this->getTokens();
        $use = array();
        $state = 'start';

        foreach ($tokens as &$token) {
            $is_array = is_array($token);

            switch ($state) {
                case 'start':
                    if ($is_array && $token[0] === T_USE) {
                        $state = 'use';
                    }
                    break;
                case 'use':
                    if ($is_array) {
                        if ($token[0] === T_VARIABLE) {
                            $use[] = substr($token[1], 1);
                        }
                    } elseif ($token == ')') {
                        break 2;
                    }
                    break;
            }
        }

        $this->useVariables = empty($use) ? $use : array_intersect_key($this->getStaticVariables(), array_flip($use));

        return $this->useVariables;
    }

    /**
     * return bool
     */
    public function isBindingRequired()
    {
        if($this->isBindingRequired === null){
            $this->getCode();
        }

        return $this->isBindingRequired;
    }

    /**
     * return bool
     */
    public function isScopeRequired()
    {
        if($this->isScopeRequired === null){
            $this->getCode();
        }

        return $this->isScopeRequired;
    }

    /**
     * @return string
     */
    protected function getHashedFileName()
    {
        if ($this->hashedName === null) {
            $this->hashedName = md5($this->getFileName());
        }

        return $this->hashedName;
    }

    /**
     * @return array
     */
    protected function getFileTokens()
    {
        $key = $this->getHashedFileName();

        if (!isset(static::$files[$key])) {
            static::$files[$key] = token_get_all(file_get_contents($this->getFileName()));
        }

        return static::$files[$key];
    }

    /**
     * @return array
     */
    protected function getTokens()
    {
        if ($this->tokens === null) {
            $tokens = $this->getFileTokens();
            $startLine = $this->getStartLine();
            $endLine = $this->getEndLine();
            $results = array();
            $start = false;

            foreach ($tokens as &$token) {
                if (!is_array($token)) {
                    if ($start) {
                        $results[] = $token;
                    }

                    continue;
                }

                $line = $token[2];

                if ($line <= $endLine) {
                    if ($line >= $startLine) {
                        $start = true;
                        $results[] = $token;
                    }

                    continue;
                }

                break;
            }

            $this->tokens = $results;
        }

        return $this->tokens;
    }

    /**
     * @return array
     */
    protected function getClasses()
    {
        $key = $this->getHashedFileName();

        if (!isset(static::$classes[$key])) {
            $this->fetchItems();
        }

        return static::$classes[$key];
    }

    /**
     * @return array
     */
    protected function getFunctions()
    {
        $key = $this->getHashedFileName();

        if (!isset(static::$functions[$key])) {
            $this->fetchItems();
        }

        return static::$functions[$key];
    }

    /**
     * @return array
     */
    protected function getConstants()
    {
        $key = $this->getHashedFileName();

        if (!isset(static::$constants[$key])) {
            $this->fetchItems();
        }

        return static::$constants[$key];
    }

    /**
     * @return array
     */
    protected function getStructures()
    {
        $key = $this->getHashedFileName();

        if (!isset(static::$structures[$key])) {
            $this->fetchItems();
        }

        return static::$structures[$key];
    }

    protected function fetchItems()
    {
        $key = $this->getHashedFileName();

        $classes = array();
        $functions = array();
        $constants = array();
        $structures = array();
        $tokens = $this->getFileTokens();

        $open = 0;
        $state = 'start';
        $prefix = '';
        $name = '';
        $alias = '';
        $isFunc = $isConst = false;

        $startLine = $endLine = 0;
        $structType = $structName = '';
        $structIgnore = false;

        $hasTraitSupport = defined('T_TRAIT');

        foreach ($tokens as $token) {
            $is_array = is_array($token);

            switch ($state) {
                case 'start':
                    if ($is_array) {
                        switch ($token[0]) {
                            case T_CLASS:
                            case T_INTERFACE:
                                $state = 'before_structure';
                                $startLine = $token[2];
                                $structType = $token[0] == T_CLASS ? 'class' : 'interface';
                                break;
                            case T_USE:
                                $state = 'use';
                                $prefix = $name = $alias = '';
                                $isFunc = $isConst = false;
                                break;
                            case T_FUNCTION:
                                $state = 'structure';
                                $structIgnore = true;
                                break;
                            default:
                                if ($hasTraitSupport && $token[0] == T_TRAIT) {
                                    $state = 'before_structure';
                                    $startLine = $token[2];
                                    $structType = 'trait';
                                }
                                break;
                        }
                    }
                    break;
                case 'use':
                    if ($is_array) {
                        switch ($token[0]) {
                            case T_FUNCTION:
                                $isFunc = true;
                                break;
                            case T_CONST:
                                $isConst = true;
                                break;
                            case T_NS_SEPARATOR:
                                $name .= $token[1];
                                break;
                            case T_STRING:
                                $name .= $token[1];
                                $alias = $token[1];
                                break;
                            case T_AS:
                                if ($name[0] !== '\\' && $prefix === '') {
                                    $name = '\\' . $name;
                                }
                                $state = 'alias';
                                break;
                        }
                    } else {
                        if ($name[0] !== '\\' && $prefix === '') {
                            $name = '\\' . $name;
                        }

                        if($token == '{') {
                            $prefix = $name;
                            $name = '';
                        } else {
                            if($isFunc){
                                $functions[$alias] = $prefix . $name;
                            } elseif ($isConst){
                                $constants[$alias] = $prefix . $name;
                            } else {
                                $classes[$alias] = $prefix . $name;
                            }
                            $name = '';
                            $state = $token == ',' ? 'use' : 'start';
                        }
                    }
                    break;
                case 'alias':
                    if ($is_array) {
                        if($token[0] == T_STRING){
                            $alias = $token[1];
                        }
                    } else {
                        if($isFunc){
                            $functions[$alias] = $prefix . $name;
                        } elseif ($isConst){
                            $constants[$alias] = $prefix . $name;
                        } else {
                            $classes[$alias] = $prefix . $name;
                        }
                        $name = '';
                        $state = $token == ',' ? 'use' : 'start';
                    }
                    break;
                case 'before_structure':
                    if ($is_array && $token[0] == T_STRING) {
                        $structName = $token[1];
                        $state = 'structure';
                    }
                    break;
                case 'structure':
                    if (!$is_array) {
                        if ($token === '{') {
                            $open++;
                        } elseif ($token === '}') {
                            if (--$open == 0) {
                                if(!$structIgnore){
                                    $structures[] = array(
                                        'type' => $structType,
                                        'name' => $structName,
                                        'start' => $startLine,
                                        'end' => $endLine,
                                    );
                                }
                                $structIgnore = false;
                                $state = 'start';
                            }
                        }
                    } else {
                        if($token[0] === T_CURLY_OPEN || $token[0] === T_DOLLAR_OPEN_CURLY_BRACES){
                            $open++;
                        }
                        $endLine = $token[2];
                    }
                    break;
            }
        }

        static::$classes[$key] = $classes;
        static::$functions[$key] = $functions;
        static::$constants[$key] = $constants;
        static::$structures[$key] = $structures;
    }

}
