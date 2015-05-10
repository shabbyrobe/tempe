<?php
namespace Tempe\Lang\Part;

use Tempe\Exception;

class JSON
{
    public $handlers = [];
    public $rules;

    function __construct($options=[])
    {
        $this->rules = [
            'json'  => ['allowValue'=>false, 'argc'=>0],
        ];

        if (isset($this->rules['json'])) {
            $this->handlers['json'] = function($handler, $in, $context) {
                if (!$in && $context->chainPos != 0) {
                    return;
                }
                $data = $context->renderer->renderTree($context->node, $context->scope);
                $ret = json_decode($data, !!'assoc');
                if ($ret === null && ($err = json_last_error()) !== 0) {
                    throw new Exception\Render("Block render could not be parsed as valid JSON", $context->node->line);
                }
                if ($context->chainPos == count($context->node->chain) - 1) {
                    if (!is_array($ret)) {
                        throw new Exception\Render("Can only merge json with scope if the json is an object", $context->node->line);
                    }
                    foreach ($ret as $k=>$v) {
                        $context->scope[$k] = $v;
                    }
                    return null;
                }
                else {
                    return $ret;
                }
            };
        }

        if ($options) {
            throw new \InvalidArgumentException("Unknown options: ".implode(', ', array_keys($options)));
        }
    }
}

