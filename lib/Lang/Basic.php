<?php
namespace Tempe\Lang;

use Tempe\Exception;
use Tempe\Renderer;
use Tempe\Lang;
use Tempe\HandlerContext;

class Basic implements Lang
{
    function __construct(array $handlers=[], array $rules=[], callable $emptyHandler=null)
    {
        $this->handlers = $handlers;
        $this->rules = $rules;
        $this->emptyHandler = $emptyHandler;
    }

    function addPart($part)
    {
        $this->addHandlers($part->handlers, $part->rules);
        return $this;
    }

    function addHandlers(array $handlers, array $rules=[])
    {
        $this->handlers = $handlers + $this->handlers;
        if ($rules) {
            $this->rules = $rules + $this->rules;
        }
        return $this;
    }

    function check($handler, $node, $chainPos)
    {
        $hid = $handler->name;

        if (!isset($this->handlers[$hid]))
            throw new Exception\Check("Handler '$hid' not found", $node->line);

        if (isset($this->rules[$hid])) {
            $rule = $this->rules[$hid];
            if (isset($rule['argc'])) {
                if ($handler->argc != $rule['argc']) {
                    throw new Exception\Check("Handler '$hid' expected {$rule['argc']} arg(s), found {$handler->argc}", $node->line);
                }
            }
            else {
                if (isset($rule['argMin']) && $handler->argc < $rule['argMin']) {
                    throw new Exception\Check("Handler '$hid' min args {$rule['argMin']}, found {$handler->argc}", $node->line);
                }
                if (isset($rule['argMax']) && $handler->argc > $rule['argMax']) {
                    throw new Exception\Check("Handler '$hid' max args {$rule['argMax']}, found {$handler->argc}", $node->line);
                }
            }

            if (isset($rule['allowValue']) && !$rule['allowValue'] && $node->type == Renderer::P_VALUE) {
                throw new Exception\Check("Handler '$hid' can not be used with a value tag", $node->line);
            }
            if (isset($rule['allowBlock']) && !$rule['allowBlock'] && $node->type == Renderer::P_BLOCK) {
                throw new Exception\Check("Handler '$hid' can not be used with a block tag", $node->line);
            }
            if (isset($rule['chainable']) && !$rule['chainable'] && ($chainPos != 0 || isset($node->chain[1]))) {
                throw new Exception\Check("Handler '$hid' is not chainable", $node->line);
            }
            if (isset($rule['last']) && isset($node->chain[$chainPos+1])) {
                throw new Exception\Check("Handlers must not follow '$hid' in a chain", $node->line);
            }
            if (isset($rule['notFirst']) && $chainPos == 0) {
                throw new Exception\Check("Handler '$hid' must not be first", $node->line);
            }
            if (isset($rule['check']) && !$rule['check']($handler, $node, $chainPos)) {
                throw new Exception\Check("Handler '$hid' check failed", $node->line);
            }
        }
    }

    function handleEmpty(HandlerContext $context)
    {
        if ($this->emptyHandler) {
            $f = $this->emptyHandler;
            return $f($context);
        }
    }

    function handle($handler, $val, HandlerContext $context)
    {
        $name = $handler->name;
        if (!isset($this->handlers[$name])) {
            throw new \Tempe\Exception\Render("Handler '$name' not found", $context->node->line);
        }
        return $this->handlers[$name]($val, $context);
    }
}

