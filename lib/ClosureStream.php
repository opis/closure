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

class ClosureStream
{
    const STREAM_PROTO = 'closure';
    
    protected static $isRegistred = false;
    
    protected $content;
     
    function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->content = "<?php \nreturn ". substr($path, strlen(static::STREAM_PROTO . '://')) . ";";
        return true;
    }
     
    public function stream_read($count)
    {
        $ret = substr($this->content, 0, $count) ;
        $count = strlen($ret);
        $this->content = substr($this->content, $count);
        return $ret;
    }
 
    public function stream_eof()
    {
        return !isset($this->content[0]);
    }
     
    public function stream_stat()
    {
        return array();
    }
     
    public static function register()
    {
        if(!static::$isRegistred)
        {
            static::$isRegistred = stream_wrapper_register(static::STREAM_PROTO, __CLASS__);
        }
    }
 
}
