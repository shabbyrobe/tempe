<?php
namespace Tempe\Lang\Part;

use Tempe\Renderer;

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

        $stringRule = ['argc'=>0, 'notFirst'=>true];
        $stringHandler = function($in, $context) use ($strings) {
            $f = $strings[$context->handler];
            return $f($in);
        };

        foreach ($strings as $handler=>$function) {
            $this->rules[$handler] = $stringRule;
            $this->handlers[$handler] = $stringHandler;
        }
    }
}
