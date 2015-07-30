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
use ReflectionFunction;
use SplFileObject;


class ReflectionClosure extends ReflectionFunction
{
    protected $code;
    protected $tokens;
    protected $classes;
    protected $useVariables;
    
    protected static $files = array();
    
    public function __construct(Closure $closure, $code = null)
    {
        $this->code = $code;
        parent::__construct($closure);
    }
    
    protected function &getFileTokens()
    {
        $file = $this->getFileName();
        $key = md5($file);
        
        if(!isset(static::$files[$key]))
        {
            static::$files[$key] = token_get_all(file_get_contents($file));
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
        if($this->classes === null)
        {
            $classes = array();
            $tokens = &$this->getFileTokens();
            
            $open = 0;
            $state = 'start';
            $class = '';
            $alias = '';
            
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
                                case T_TRAIT:
                                    $state = 'structure';
                                    break;
                                case T_USE:
                                    $state = 'use';
                                    $class = $alias = '';
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
                    case 'structure':
                        if(!$is_array)
                        {
                            if($token === '{')
                            {
                                $open++;
                            }
                            elseif($token === '{')
                            {
                                if(--$open == 0)
                                {
                                    $state = 'start';
                                }
                            }
                        }
                        break;
                }
            }
            
            $this->classes = $classes;

        }
        
        return $this->classes;
    }
    
    public function getCode()
    {
        if($this->code === null)
        {
            $fileName = $this->getFileName();
            $line = $this->getStartLine() - 1;
            
            if($line === 1 && strpos($fileName, ClosureStream::STREAM_PROTO . '://') === 0)
            {
                return $this->code = substr($fileName, strlen(ClosureStream::STREAM_PROTO) + 3);
            }
            
            if(null !== $className = $this->getClosureScopeClass())
            {
                $className = '\\' . trim($className->getName(), '\\');
            }
            
            $ns = $this->getNamespaceName();
            $nsf = $ns == '' ? '' : ($ns[0] == '\\' ? $ns : '\\' . $ns);
            
            $_file = var_export($fileName, true);
            $_dir = var_export(dirname($fileName), true);
            $_namespace = var_export($ns, true);
            $_class = var_export($className, true);
            
            
            $tokens = &$this->getTokens();
            $state = 'start';
            $open = 0;
            $code = '';
            $buffer = $cls = '';
            $new_key_word = false;
            $classes = null;
            $use = array();
            
            foreach($tokens as &$token)
            {
                $is_array = is_array($token);
                
                switch($state)
                {
                    case 'start':
                        if($is_array && $token[0] === T_FUNCTION)
                        {
                            $code .= $token[1];
                            $state = 'function';
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
                                    $token[1] = $token[2] - $line;
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
                                case T_COMMENT:
                                    if(substr($token[1], 0, 8) === '#trackme')
                                    {
                                        $timestamp = time();
                                        $token[1]  = '/**' . PHP_EOL;
                                        $token[1] .= '* Date      : ' . date(DATE_W3C, $timestamp) . PHP_EOL;
                                        $token[1] .= '* Timestamp : ' . $timestamp . PHP_EOL;
                                        $token[1] .= '* Line      : ' . ($line + 1) . PHP_EOL;
                                        $token[1] .= '* File      : ' . $_file . PHP_EOL . '*/';     
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
                        
                        if($cls[0] == '\\')
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
