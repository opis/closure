<?php
/* ===========================================================================
 * Opis Project
 * http://opis.io
 * ===========================================================================
 * Copyright (c) 2014-2015 Opis Project
 * 
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure;

use Closure;
use SplFileObject;
use ReflectionFunction;

class ReflectionClosure extends ReflectionFunction
{
    protected $code;
    protected $tokens;
    protected $hashedName;
    protected $useVariables;
    protected $isStaticClosure;
    
    protected static $files = array();
    protected static $classes = array();
    protected static $structures = array();
    
    public function __construct(Closure $closure, $code = null)
    {
        $this->code = $code;
        parent::__construct($closure);
    }
    
    protected function getHashedFileName()
    {
        if($this->hashedName === null)
        {
            $this->hashedName = md5($this->getFileName());
        }
        
        return $this->hashedName;
    }
    
    protected function &getFileTokens()
    {
        $key = $this->getHashedFileName();
        
        if(!isset(static::$files[$key]))
        {
            static::$files[$key] = token_get_all(file_get_contents($this->getFileName()));
        }
        
        return static::$files[$key];
    }
    
    protected function &getTokens()
    {
        if($this->tokens === null)
        {
            $tokens = &$this->getFileTokens();
            $startLine = $this->getStartLine();
            $endLine = $this->getEndLine();
            $results = array();
            $start = false;
            
            foreach($tokens as &$token)
            {
                if(!is_array($token))
                {
                    if($start)
                    {
                        $results[] = $token;
                    }
                    
                    continue;
                }
                
                $line = $token[2];
                
                if($line <= $endLine)
                {
                    if($line >= $startLine)
                    {
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
    
    protected function &getClasses()
    {
        $key = $this->getHashedFileName();
        
        if(!isset(static::$classes[$key]))
        {
            $classes = array();
            $structures = array();
            $tokens = &$this->getFileTokens();
            
            $open = 0;
            $state = 'start';
            $class = '';
            $alias = '';
            
            $startLine = $endLine = 0;
            $structType = $structName = '';
            
            $hasTraitSupport = defined('T_TRAIT');
            
            foreach($tokens as &$token)
            {
                $is_array = is_array($token);
                
                switch($state)
                {
                    case 'start':
                        if($is_array)
                        {
                            switch($token[0])
                            {
                                case T_CLASS:
                                case T_INTERFACE:
                                    $state = 'before_structure';
                                    $startLine = $token[2];
                                    $structType = $token[0] == T_CLASS ? 'class' : 'interface';
                                    break;
                                case T_USE:
                                    $state = 'use';
                                    $class = $alias = '';
                                    break;
                                default:
                                    if($hasTraitSupport && $token[0] == T_TRAIT)
                                    {
                                        $state = 'before_structure';
                                        $startLine = $token[2];
                                        $structType = 'trait';
                                    }
                                    break;
                            }
                        }
                        break;
                    case 'use':
                        if($is_array)
                        {
                            switch($token[0])
                            {
                                case T_NS_SEPARATOR:
                                    $class .= $token[1];
                                    break;
                                case T_STRING:
                                    $class .= $token[1];
                                    $alias = $token[1];
                                    break;
                                case T_AS:
                                    $state = 'alias';
                                    break;
                            }
                        }
                        else
                        {
                            if($class[0] !== '\\')
                            {
                                $class = '\\' . $class;
                            }
                            
                            $classes[$alias] = $class;
                            
                            $state = $token == ',' ? 'use' : 'start';
                        }
                        break;
                    case 'alias':
                        if($is_array)
                        {
                            switch($token[0])
                            {
                                case T_STRING:
                                    $alias = $token[1];
                                    break;
                            }
                        }
                        else
                        {
                            if($class[0] !== '\\')
                            {
                                $class = '\\' . $class;
                            }
                            
                            $classes[$alias] = $class;
                            
                            $state = $token == ',' ? 'use' : 'start';
                        }
                        break;
                    case 'before_structure':
                        if($is_array && $token[0] == T_STRING)
                        {
                            $structName = $token[1];
                            $state = 'structure';
                        }
                        break;
                    case 'structure':
                        if(!$is_array)
                        {
                            if($token === '{')
                            {
                                $open++;
                            }
                            elseif($token === '}')
                            {
                                if(--$open == 0)
                                {
                                    $structures[] = array(
                                        'type' => $structType,
                                        'name' => $structName,
                                        'start' => $startLine,
                                        'end' => $endLine,
                                    );
                                    
                                    $state = 'start';
                                }
                            }
                        }
                        else
                        {
                            $endLine = $token[2];
                        }
                        break;
                }
            }
            
            static::$classes[$key] = $classes;
            static::$structures[$key] = $structures;
        }
        
        return static::$classes[$key];
    }
    
    protected function &getStructures()
    {
        $key = $this->getHashedFileName();
        
        if(!isset(static::$structures[$key]))
        {
            $this->getClasses();
        }
        
        return static::$structures[$key];
    }
    
    public function isStatic()
    {
        if($this->isStaticClosure === null)
        {
            $this->isStaticClosure = strtolower(substr($this->getCode(), 0, 6)) === 'static';
        }
        
        return $this->isStaticClosure;
    }
    
    public function getCode()
    {
        if($this->code === null)
        {
            $fileName = $this->getFileName();
            $line = $this->getStartLine() - 1;
            
            $match = ClosureStream::STREAM_PROTO . '://';
            
            if($line === 1 && substr($fileName, 0, strlen($match)) === $match)
            {
                return $this->code = substr($fileName, strlen($match));
            }
            
            $className = null;
            
            if(SerializableClosure::supportBinding())
            {
                if(null !== $className = $this->getClosureScopeClass())
                {
                    $className = '\\' . trim($className->getName(), '\\');
                }
            }
            
            
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
            $tokens = &$this->getTokens();
            $state = 'start';
            $open = 0;
            $code = '';
            $buffer = $cls = '';
            $new_key_word = false;
            $classes = null;
            $use = array();
            $lineAdd = 0;
            
            foreach($tokens as &$token)
            {
                $is_array = is_array($token);
                
                switch($state)
                {
                    case 'start':
                        if($is_array && ($token[0] === T_FUNCTION || $token[0] === T_STATIC))
                        {
                            $code .= $token[1];
                            $state = $token[0] === T_FUNCTION ? 'function' : 'static';
                        }
                        break;
                    case 'static':
                        if($is_array && ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_FUNCTION))
                        {
                            $code .= $token[1];
                           
                            if($token[0] === T_FUNCTION)
                            {
                                $state = 'function';
                            }
                        }
                        else
                        {
                            $code = '';
                            $state = 'start';
                        }
                        break;
                    case 'function':
                        if($is_array)
                        {
                            $code .= $token[1];
                            
                            if($token[0] === T_STRING)
                            {
                                $state = 'named_function';
                                $code = '';
                            }
                            
                        }
                        else
                        {
                            $code .= $token;
                            
                            if($token === '(')
                            {
                                $state = 'closure';
                            }
                        }
                        break;
                    case 'named_function':
                        if(!$is_array)
                        {
                            if($token === '{')
                            {
                                $open++;
                            }
                            elseif($token === '}')
                            {
                                if(--$open === 0)
                                {
                                    $state = 'start';
                                }
                            }
                        }
                        break;
                    case 'closure':
                        if($is_array)
                        {
                            switch ($token[0])
                            {
                                case T_LINE:
                                    $token[1] = $token[2] - $line + $lineAdd;
                                    break;
                                case T_FILE:
                                    $token[1] = $_file;
                                    break;
                                case T_DIR:
                                    $token[1] = $_dir;
                                    break;
                                case T_NS_C:
                                    $token[1] = $_namespace;
                                    break;
                                case T_CLASS_C:
                                    $token[1] = $_class;
                                    break;
                                case T_FUNC_C:
                                    $token[1] = $_function;
                                    break;
                                case T_METHOD_C:
                                    $token[1] = $_method;
                                    break;
                                case T_COMMENT:
                                    if(substr($token[1], 0, 8) === '#trackme')
                                    {
                                        $timestamp = time();
                                        $token[1]  = '/**' . PHP_EOL;
                                        $token[1] .= '* Date      : ' . date(DATE_W3C, $timestamp) . PHP_EOL;
                                        $token[1] .= '* Timestamp : ' . $timestamp . PHP_EOL;
                                        $token[1] .= '* Line      : ' . ($line + 1) . PHP_EOL;
                                        $token[1] .= '* File      : ' . $_file . PHP_EOL . '*/' . PHP_EOL;
                                        $lineAdd += 5;
                                    }
                                    break;
                                case T_USE:
                                    $state = 'use';
                                    break;
                                case T_NS_SEPARATOR:
                                case T_STRING:
                                    $buffer = $cls = $token[1];
                                    $new_key_word = false;
                                    $state = 'class_name';
                                    break 2;
                                case T_NEW:
                                    $buffer = $token[1];
                                    $new_key_word = true;
                                    $state = 'new';
                                    break 2;
                                default:
                                    if($hasTraitSupport && $token[0] == T_TRAIT_C)
                                    {
                                        if($_trait === null)
                                        {
                                            $startLine = $this->getStartLine();
                                            $endLine = $this->getEndLine();
                                            $structures = &$this->getStructures();
                                            
                                            $_trait = '';
                                            
                                            foreach($structures as &$struct)
                                            {
                                                if($struct['type'] === 'trait' &&
                                                   $struct['start'] <= $startLine &&
                                                   $struct['end'] >= $endLine)
                                                {
                                                    $_trait = ($ns == '' ? '' : $ns . '\\') . $struct['name'];
                                                    break;
                                                }
                                            }
                                            
                                            $_trait = var_export($_trait, true);
                                        }
                                        
                                        $token[1] = $_trait;
                                    }
                            }
                            
                            $code .= $token[1];
                        }
                        else
                        {   
                            $code .= $token;
                            
                            if($token === '{')
                            {
                                $open++;
                            }
                            elseif($token === '}')
                            {
                                if(--$open === 0)
                                {
                                    break 2;
                                }
                            }
                        }
                        break;
                    case 'use':
                        if($is_array)
                        {
                            if($token[0] === T_VARIABLE)
                            {
                                $use[] = substr($token[1], 1);
                            }
                            
                            $code .= $token[1];
                        }
                        else
                        {
                            if($token == ')')
                            {
                                $state = 'closure';
                            }
                            
                            $code .= $token;
                        }
                        break;
                    case 'class_name':
                        
                        if($is_array)
                        {
                            switch($token[0])
                            {
                                case T_NS_SEPARATOR:
                                case T_STRING:
                                    $cls .= $token[1];
                                    $buffer .= $token[1];
                                    break 2;
                                case T_WHITESPACE:
                                    $buffer .= $token[1];
                                    break 2;
                            }
                        }
                        
                        if($cls[0] == '\\' || $cls == 'static' || $cls == 'self')
                        {
                            $code .= $buffer . ($is_array ? $token[1] : $token);
                        }
                        else
                        {
                            
                            if($new_key_word || ($is_array && ($token[0] == T_VARIABLE || $token[0] == T_DOUBLE_COLON)))
                            {
                                $suffix = substr($buffer, strlen(rtrim($buffer)));
                                
                                if($classes === null)
                                {
                                    $classes = &$this->getClasses();
                                }
                                
                                if(isset($classes[$cls]))
                                {
                                    $cls = $classes[$cls];
                                }
                                else
                                {
                                    $cls = $nsf . '\\' . $cls; 
                                }
                                
                                $code .= $cls . $suffix . ($is_array ? $token[1] : $token);   
                            }
                            else
                            {
                                $code .= $buffer . ($is_array ? $token[1] : $token);
                            }
                            
                        }
                        
                        $state = 'closure';
                        break;
                    case 'new':
                        if($is_array)
                        {
                            switch($token[0])
                            {
                                case T_WHITESPACE:
                                    $buffer .= $token[1];
                                    break;
                                case T_NS_SEPARATOR:
                                case T_STRING:
                                    $code .= $buffer;
                                    $buffer = $cls = $token[1];
                                    $state = 'class_name';
                                    break 2;
                                default:
                                    $code .= $buffer . $token[1];
                                    $state = 'closure';
                                    break;
                            }
                        }
                        else
                        {
                            $code .= $buffer . $token;
                            $state = 'closure';
                        }
                        break;
                }
            }
            
            $this->code = $code;
            $this->useVariables = empty($use) ? $use : array_intersect_key($this->getStaticVariables(), array_flip($use));
        }
        
        return $this->code;
    }

    public function getUseVariables()
    {
        if($this->useVariables === null)
        {
            $tokens = &$this->getTokens();
            $use = array();
            $state = 'start';
            
            foreach($tokens as &$token)
            {
                $is_array = is_array($token);
                
                switch($state)
                {
                    case 'start':
                        if($is_array && $token[0] === T_USE)
                        {
                            $state = 'use';
                        }
                        break;
                    case 'use':
                        if($is_array)
                        {
                            if($token[0] === T_VARIABLE)
                            {
                                $use[] = substr($token[1], 1);
                            }
                        }
                        elseif($token == ')')
                        {
                            break 2;
                        }
                        break;
                }
            }
            
            $this->useVariables = empty($use) ? $use : array_intersect_key($this->getStaticVariables(), array_flip($use));
        }
        
        return $this->useVariables;
    }
    
}
