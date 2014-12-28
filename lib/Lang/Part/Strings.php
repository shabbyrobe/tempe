<?php
namespace Tempe\Lang\Part;

class Strings
{
    public $rules = [];
    public $handlers = [];

    public function __construct()
    {
        $strings = [
            'upper'=>'strtoupper',
            'lower'=>'strtolower',
            'ucfirst'=>'ucfirst',
            'lcfirst'=>'lcfirst',
            'ucwords'=>'ucwords',

            'trim'=>'trim',
            'ltrim'=>'ltrim',
            'rtrim'=>'rtrim',

            'rev'=>'strrev',

            'nl2br'=>'nl2br',
            'striptags'=>'strip_tags',
            'base64'=>'base64_encode',
        ];

        $stringRule = ['argc'=>0];
        $stringHandler = function($in, $context) {
            $f = $context->args[0];
            return $f($in);
        };

        foreach ($strings as $handler=>$function) {
            $this->rules[$handler] = $stringRule;
            $this->handlers[$handler] = $stringHandler;
        }
    }
}
