<?php
namespace Tempe\Lang\Part;

use Tempe\Renderer;

class Strings
{
    public $rules = [];
    public $handlers = [];

    public function __construct()
    {
        $stringFuncs = [
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
        foreach ($stringFuncs as $handler=>$function) {
            $this->rules[$handler] = $stringRule;
            $this->handlers[$handler] = function($handler, $in, $context) use ($function) {
                return $function($in);
            };
        }

        $this->rules['nl2spc'] = $stringRule;
        $this->handlers['nl2spc'] = function($handler, $in, $context) {
            return trim(preg_replace("/\n+/", " ", $in));
        };
    }
}
