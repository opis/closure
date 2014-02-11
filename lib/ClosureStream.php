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
    
    protected $length;
    
    protected $pointer = 0;
    
    
    function stream_open($path, $mode, $options, &$opened_path)
    {   
        $this->content = "<?php\nreturn ". substr($path, strlen(static::STREAM_PROTO . '://')) . ";";
        $this->length = strlen($this->content);
        return true;
    }
     
    public function stream_read($count)
    {
        $value = substr($this->content, $this->pointer, $count);
        $this->pointer += $count;
        return $value;
    }
 
    public function stream_eof()
    {
        return $this->pointer >= $this->length;
    }
     
    public function stream_stat()
    {
        $stat = stat(__FILE__);
        $stat[7] = $stat['size'] = $this->length;
        return $stat;
    }
    
    public function url_stat($path, $flags)
    {
        $stat = stat(__FILE__);
        $stat[7] = $stat['size'] = $this->length;
        return $stat;
    }
    
    public static function register()
    {
        if(!static::$isRegistred)
        {
            static::$isRegistred = stream_wrapper_register(static::STREAM_PROTO, __CLASS__);
        }
    }
 
}
