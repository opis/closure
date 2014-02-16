<?php
/* ===========================================================================
 * Opis Project
 * http://opis.io
 * ===========================================================================
 * Copyright (c) 2014 Opis Project
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

    protected $useVariables;
    
    public function __construct(Closure $closure, $code = null)
    {
        $this->code = $code;
        parent::__construct($closure);
    }
    
    public function getCode()
    {
        if($this->code === null)
        {
            
            $_file_ = $this->getFileName();
            if (strpos($_file_, ClosureStream::STREAM_PROTO . '://') === 0)
            {
                return $this->code = substr($_file_, strlen(ClosureStream::STREAM_PROTO) + 3);
            }
            $_dir_ = dirname($_file_);
            $_line_ = $this->getStartLine() - 1;
            
            $file = new SplFileObject($_file_);
            $file->seek($_line_);
            $code = '<?php ';
            $end_line = $this->getEndLine();
            while ($file->key() < $end_line)
            {
                $code .= $file->current();
                $file->next();
            }
            $file = null;
            $_file_ = var_export($_file_, true);
            $_dir_ = var_export($_dir_, true);
            $_namespace_ = var_export($this->getNamespaceName(), true);
            $tokens = token_get_all($code);
            $state = 'start';
            $open = 0;
            $code = '';
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
                                    $token[1] = $_line_ + $token[2];
                                    break;
                                case T_DIR:
                                    $token[1] = $_dir_;
                                    break;
                                case T_FILE:
                                    $token[1] = $_file_;
                                    break;
                                case T_NS_C:
                                    $token[1] = $_namespace_;
                                    break;
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
                }
                
            }
            
            $this->code = $code;
        }
        
        return $this->code;
    }
    
    public function getUseVariables()
    {
        if($this->useVariables === null)
        {
            $code = $this->getCode();
            
            $tokens = token_get_all('<?php ' . substr($code, 0, strpos($code, '{')));
            
            $state = 'start';
            
            $use = array();
            
            foreach($tokens as &$token)
            {
                if(is_array($token))
                {
                    switch($state)
                    {
                        case 'start':
                            if($token[0] === T_USE)
                            {
                                $state = 'use';
                            }
                            break;
                    case 'use':
                            if($token[0] === T_VARIABLE)
                            {
                                $use[] = substr($token[1], 1);
                            }
                            break;
                    }
                }
            }
            
            $this->useVariables = empty($use) ? $use : array_intersect_key($this->getStaticVariables(), array_flip($use));
        }
        
        return $this->useVariables;
    }
    
}
