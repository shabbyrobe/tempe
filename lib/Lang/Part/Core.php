<?php
namespace Tempe\Lang\Part;

use Tempe\Exception;

class Core
{
    public $handlers = [];
    public $rules;

    function __construct($options=[])
    {
        $this->rules = [
            'get'   => ['argMin'=>0, 'argMax'=>1],
            'eqval' => ['argc'=>1],
            'eqvar' => ['argc'=>1],
            'not'   => ['argc'=>0],
            'each'  => ['argMin'=>0, 'argMax'=>1, 'allowValue'=>false],
            'as'    => ['argMin'=>1, 'first'=>false],
            'push'  => ['argc'=>1, 'chainable'=>false],
            'show'  => ['argc'=>0, 'allowValue'=>false],
            'set'   => ['argc'=>1, 'last'=>true],
        ];

        { // whitelisting & blacklisting
            if (isset($options['whitelist'])) {
                if (isset($options['blacklist'])) {
                    throw new \InvalidArgumentException("Only specify whitelist or blacklist, not both");
                }

                $active = [];
                foreach ($options['whitelist'] as $handler) {
                    if (!isset($this->rules[$handler])) {
                        throw new \InvalidArgumentException("Unknown handler $handler");
                    }

                    $active[$handler] = $this->rules[$handler];
                }
                $this->rules = $active;
                unset($options['whitelist']);
            }
            elseif (isset($options['blacklist'])) {
                foreach ($options['blacklist'] as $handler) {
                    if (!isset($this->rules[$handler])) {
                        throw new \InvalidArgumentException("Unknown handler $handler");
                    }

                    unset($this->rules[$handler]);
                }
                unset($options['blacklist']);
            }
        }

        if (isset($this->rules['get'])) {
            $this->handlers['get'] = function($handler, $in, $context) {
                $scopeInput = false;
                $key = isset($handler->args[0]) ? $handler->args[0] : null;
                if ($key && $in !== '' && $in !== null) {
                    $scopeInput = true;
                    $scope = $in;
                }
                else {
                    $scope = $context->scope;
                }

                if ($in && !$key) {
                    $key = $in;
                }
                if (!is_array($scope) && !$scope instanceof \ArrayAccess) {
                    throw new Exception\Render("Input scope was not an array or ArrayAccess", $context->node->line);
                }
                if (!array_key_exists($key, $scope)) {
                    throw new Exception\Render("'get' could not find key '$key' in ".($scopeInput ? 'input' : 'context')." scope", $context->node->line);
                }
                return $scope[$key];
            };
        }

        if (isset($this->rules['eqvar'])) {
            $this->handlers['eqvar'] = function($handler, $in, $context) {
                $key = $handler->args[0];
                if (!array_key_exists($key, $context->scope)) {
                    throw new Exception\Render("'eqvar' could not find key '$key' in scope", $context->node->line);
                }
                $yep = is_object($in) 
                    ? $in == $context->scope[$key]
                    : $in === $context->scope[$key];

                if ($yep) {
                    return true;
                } else {
                    $context->break = true;
                }
            };
        }

        if (isset($this->rules['eqval'])) {
            $this->handlers['eqval'] = function($handler, $in, $context) {
                $yep = $in == $handler->args[0];
                if ($yep) {
                    return true;
                } else {
                    $context->break = true;
                }
            };
        }

        if (isset($this->rules['not'])) {
            $this->handlers['not'] = function($handler, $in, $context) {
                return !$in;
            };
        }

        if (isset($this->rules['set'])) {
            $this->handlers['set'] = function($handler, $in, $context) {
                $key = $handler->args[0];
                if ($context->chainPos == 0 && $context->node->type == \Tempe\Renderer::P_BLOCK) {
                    $context->scope[$key] = $context->renderer->renderTree($context->node, $context->scope);
                } else {
                    $context->scope[$key] = $in;
                }
            };
        }

        if (isset($this->rules['each'])) {
            $this->handlers['each'] = function($handler, $in, $context) {
                $key = $handler->argc == 1 ? $handler->args[0] : null;
                $iter = null;

                if ($in) {
                    $iter = $in;
                }
                elseif ($key) {
                    if (!array_key_exists($key, $context->scope))
                        throw new Exception\Render("'each' could not find key '$key' in scope", $context->node->line);
                    $iter = $context->scope[$key];
                }

                if (!$iter) {
                    return;
                }
                if (!is_array($iter) && !$iter instanceof \Traversable) {
                    throw new Exception\Render("'each' was not traversable", $context->node->line);
                }

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
                        foreach ((array) $loop['_value_'] as $k=>$v) {
                            $scope[$k] = $v;
                        }
                    }

                    $out .= $context->renderer->renderTree($context->node, $scope);
                    ++$idx;
                }
                return $out;
            };
        }

        if (isset($this->rules['push'])) {
            $this->handlers['push'] = function($handler, $in, $context) {
                $scope = &$context->scope[$handler->args[0]];
                return $context->renderer->renderTree($context->node, $scope);
            };
        }

        if (isset($this->rules['show'])) {
            $this->handlers['show'] = function($handler, $in, $context) {
                if ($context->chainPos == 0 || $in) {
                    return $context->renderer->renderTree($context->node, $context->scope);
                }
            };
        }

        if (isset($this->rules['as'])) {
            $this->handlers['as'] = function($handler, $in, $context) {
                static $e;
                if (!$e) {
                    $e = new \Tempe\Filter\WebEscaper;
                }
                if (isset($handler->args[1])) {
                    foreach ($handler->args as $arg) {
                        $in = $e->{$arg}($in);
                    }
                    return $in;
                }
                else {
                    return $e->{$handler->args[0]}($in);
                }
            };
        }

        if ($options) {
            throw new \InvalidArgumentException("Unknown options: ".implode(', ', array_keys($options)));
        }
    }
}
