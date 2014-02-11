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
    
    protected $useRefs;
    
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
                                    break 2;
                                }
                            }
                        }
                        else
                        {
                            $code .= $token[1];
                        }
                        break;
                }
                
            }
            
            $this->code = $code;
        }
        
        return $this->code;
    }
    
    public function getUseReferences()
    {
        if($this->useRefs === null)
        {
            $statics = $this->getStaticVariables();
            $this->useRefs = array();
            if(!empty($statics))
            {
                $this->useRefs = array();
                
                $tokens = token_get_all('<?php ' . $this->getCode());
                
                $state = 'start';
                $open = 0;
                
                foreach($tokens as &$token)
                {
                    switch($state)
                    {
                        case 'start':
                            if(is_array($token))
                            {
                                if($token[0] === T_USE)
                                {
                                    $state = 'use';
                                }
                            }
                            break;
                        case 'use':
                            if(!is_array($token))
                            {
                                switch($token)
                                {
                                    case '(':
                                        $open++;
                                        break;
                                    case ')':
                                        $open--;
                                        if($open === 0)
                                        {
                                            break 3;
                                        }
                                        break;
                                    case '&':
                                        $state = 'var';
                                        break 2;
                                }
                            }
                            break;
                        case 'var':
                            if(is_array($token) && $token[0] === T_VARIABLE)
                            {
                                $this->useRefs[] = substr($token[1], 1);
                                $state = 'use';
                            }
                            break;
                    }
                }
            }
        }
        
        return $this->useRefs;
    }
    
}
