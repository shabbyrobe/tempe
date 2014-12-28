<?php
namespace Tempe;

abstract class Exception extends \RuntimeException
{
    function __construct($message='', $line=null, $code=null, \Exception $previous=null)
    {
        if ($line)
            $message .= " at line {$line}";

        parent::__construct(trim($message), $code, $previous);
    }
}
