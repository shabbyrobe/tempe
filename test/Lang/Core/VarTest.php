<?php
namespace Tempe\Test\Lang\Core;

use Tempe\Lang;
use Tempe\HandlerContext;

class VarTest extends \PHPUnit_Framework_TestCase
{
    private function createContext(&$scope, $args=[], $renderer=null)
    {
        $ctx = new HandlerContext;
        $ctx->scope = &$scope;
        $ctx->renderer = $renderer;
        $ctx->args = (array) $args;
        $ctx->argc = count($ctx->args);
        $ctx->node = (object)[
            'line'=>1,
        ];
        return $ctx;
    }

    function testHandlerVarInputAsKey()
    {
        $c = new Lang\Part\Core();
        $vars = ['foo'=>'yep'];
        $context = $this->createContext($vars);
        $result = $c->handlers['var']('foo', $context);
        $this->assertEquals('yep', $result);
    }

    function testHandlerVarInputAsScope()
    {
        $c = new Lang\Part\Core();
        $vars = ['foo'=>'bar'];
        $context = $this->createContext($vars, 'bar');
        $result = $c->handlers['var'](['bar'=>'yep'], $context);
        $this->assertEquals('yep', $result);
    }

    function testHandlerVarFromScope()
    {
        $c = new Lang\Part\Core();
        $vars = ['foo'=>'yep'];
        $context = $this->createContext($vars, 'foo');
        $result = $c->handlers['var']('', $context);
        $this->assertEquals('yep', $result);
    }

    function testValueHandlerOutputMissingContextKeyFails()
    {
        $c = new Lang\Part\Core();
        $vars = [];
        $context = $this->createContext($vars, 'foo');

        $this->setExpectedException('Tempe\Exception\Render', "'var' could not find key 'foo' in context scope");
        $result = $c->handlers['var']('', $context);
    }

    function testValueHandlerOutputMissingInputKeyFails()
    {
        $c = new Lang\Part\Core();
        $vars = [];
        $context = $this->createContext($vars, 'foo');

        $this->setExpectedException('Tempe\Exception\Render', "'var' could not find key 'foo' in input scope");
        $result = $c->handlers['var']([], $context);
    }
}
