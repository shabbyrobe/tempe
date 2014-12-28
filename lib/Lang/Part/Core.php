<?php
namespace Tempe\Lang\Part;

class Core
{
    function __construct()
    {
        $this->rules = [
            'var'  => ['argc'=>1],
            'dump' => ['argc'=>0],
            'is'   => ['argc'=>1],
            'not'  => ['argc'=>1],
            'each' => ['argMin'=>0, 'argMax'=>1, 'allowValue'=>false],
            'set'  => ['argc'=>1],
            'as'   => ['argc'=>1],
            'push' => ['argc'=>1, 'chainable'=>false],
            'show' => ['argc'=>0, 'allowValue'=>false],
            'hide' => ['argc'=>0, 'allowValue'=>false],
        ];

        $this->handlers = [
            'var'=>function($in, $context) {
                $key = $context->args[0];
                if ($in && isset($in[$key]))
                    return $in[$key];
                if (isset($context->scope[$key]))
                    return $context->scope[$key];
            },

            'dump'=>function($in, $context) {
                ob_start();
                var_dump($in);
                return ob_get_clean();
            },

            'is'=>function($in, $context) {
                if ($in != $context->args[0])
                    $context->stop = true;
                else
                    return $in;
            },

            'not'=>function($in, $context) {
                if ($in == $context->args[0])
                    $context->stop = true;
                else
                    return $in;
            },

            'set'=>function($in, $context) {
                $key = $context->args[0];
                if ($context->node->type == \Tempe\Renderer::P_BLOCK)
                    $context->scope[$key] = $context->renderer->renderTree($context->node);
                else
                    $context->scope[$key] = $in;
            },

            'each'=>function($in, $context) {
                $key = $context->argc == 1 ? $context->args[0] : null;
                if ($in)
                    $iter = $in;
                elseif ($key)
                    $iter = $context->scope[$key];
                else
                    return;

                $out = '';
                $idx = 0;

                // add or remove ampersand to switch 'each' hoisting
                $scope = $context->scope;

                $loop = ['_key_'=>null, '_value_'=>null, '_idx_'=>0, '_num_'=>1, '_first_'=>true];

                foreach ($iter as $loop['_key_']=>$loop['_value_']) {
                    $scope['_key_']   = $loop['_key_'];
                    $scope['_value_'] = $loop['_value_'];
                    $scope['_idx_']   = $loop['_idx_'] = $idx;
                    $scope['_num_']   = $loop['_num_'] = $idx + 1;
                    $scope['_first_'] = $loop['_first_'] = $idx == 0;
                    $scope['_loop_']  = $loop;

                    if (!is_scalar($loop['_value_'])) {
                        foreach ((array) $loop['_value_'] as $k=>$v)
                            $scope[$k] = $v;
                    }

                    $out .= $context->renderer->renderTree($context->node, $scope);
                    ++$idx;
                }
                return $out;
            },

            'push'=>function($in, $context) {
                $scope = &$context->scope[$context->args[0]];
                return $context->renderer->renderTree($context->node, $scope);
            },

            'show'=>function($in, $context) {
                return $context->renderer->renderTree($context->node, $context->scope);
            },

            'hide'=>function($in, $context) {
                return $context->renderer->renderTree($context->node, $context->scope);
            },

            'as'=>function($in, $context) {
                static $e;
                if (!$e)
                    $e = new \Tempe\Filter\WebEscaper;
                return $e->{$context->args[0]}($in);
            },
        ];

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
