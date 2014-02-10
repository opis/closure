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

class ReflectionClosure extends ReflectionFunction
{
    protected $code;
    
    public function __construct(Closure $closure)
    {
        parent::__construct($closure);
    }
    
    public function getCode()
    {
        if($this->code === null)
        {
            $code = '<?php ';
            $file = new SplFileObject($this->getFileName());
            $file->seek($this->getStartLine()-1);
            
            while ($file->key() < $this->getEndLine())
            {
                $code .= $file->current();
                $file->next();
            }
            
            $tokens = token_get_all($code);
            $state = 'start';
            $open = 0;
            $code = '';
            $break = false;
            
            foreach($tokens as &$token)
            {
                switch($state)
                {
                    case 'start':
                        if(is_array($token) && $token[0] === T_FUNCTION)
                        {
                            $code .= $token[1];
                            $state = 'function';
                        }
                        break;
                    case 'function':
                        if(is_array($token))
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
                        if(!is_array($token))
                        {
                            if($token === '{')
                            {
                                $open++;
                            }
                            elseif($token === '}')
                            {
                                $open--;
                                if($open === 0)
                                {
                                    $state = 'start';
                                }
                            }
                        }
                        break;
                    case 'closure':
                        if(!is_array($token))
                        {
                            $code .= $token;
                            if($token === '{')
                            {
                                $open++;
                            }
                            elseif($token === '}')
                            {
                                $open--;
                                if($open === 0)
                                {
                                    $break = true;
                                }
                            }
                        }
                        else
                        {
                            $code .= $token[1];
                        }
                        break;
                }
                
                if($break === true)
                {
                    break;
                }
                
            }
            
            $this->code = $code;
        }
        
        return $this->code;
    }
    
}