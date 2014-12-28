<?php
namespace Tempe\Lang;

use Tempe\Exception;
use Tempe\Renderer;
use Tempe\Lang;
use Tempe\HandlerContext;

class Basic implements Lang
{
    function __construct(array $handlers=[], array $rules=[])
    {
        $this->handlers = $handlers;
        $this->rules = $rules;
    }

    function addPart($part)
    {
        $this->handlers = $part->handlers + $this->handlers;
        if ($part->rules)
            $this->rules = $part->rules + $this->rules;
        return $this;
    }

    function addHandlers(array $handlers, array $rules=[])
    {
        $this->handlers = $handlers + $this->handlers;
        if ($rules)
            $this->rules = $rules + $this->rules;
        return $this;
    }

    function check(array $handler, $node, $chainPos)
    {
        $hid = $handler['handler'];

        if (!isset($this->handlers[$hid]))
            throw new Exception\Check("Handler $hid not found", $node->line);

        if (isset($this->rules[$hid])) {
            $rule = $this->rules[$hid];
            if (isset($rule['argc'])) {
                if ($handler['argc'] != $rule['argc'])
                throw new Exception\Check("Handler '$hid' expected {$rule['argc']} arg(s), found {$handler['argc']} on line {$node->line}");
            }
            else {
                if (isset($rule['argMin']) && $handler['argc'] < $rule['argMin'])
                    throw new Exception\Check("Handler '$hid' min args {$rule['argMin']}, found {$handler['argc']} on line {$node->line}");

                if (isset($rule['argMax']) && $handler['argc'] > $rule['argMax'])
                    throw new Exception\Check("Handler '$hid' max args {$rule['argMax']}, found {$handler['argc']} on line {$node->line}");
            }

            if (isset($rule['allowValue']) && !$rule['allowValue'] && $node->type == Renderer::P_VALUE)
                throw new Exception\Check("Handler '$hid' can not be used with a value tag on line {$node->line}");
            
            if (isset($rule['allowBlock']) && !$rule['allowBlock'] && $node->type == Renderer::P_BLOCK)
                throw new Exception\Check("Handler '$hid' can not be used with a block tag on line {$node->line}");

            if (isset($rule['chainable']) && !$rule['chainable'] && ($chainPos != 0 || isset($node->chain[1])))
                throw new Exception\Check("Handler '$hid' is not chainable on line {$node->line}");

            if (isset($rule['check']) && !$rule['check']($handler, $node, $chainPos))
                throw new Exception\Check("Handler '$hid' check failed on line {$node->line}");
        }
    }

    function handle(array $handler, $val, HandlerContext $context)
    {
        return $this->handlers[$handler['handler']]($val, $context);
    }
}

