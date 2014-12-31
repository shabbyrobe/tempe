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
        $ctx->node = (object)[
            'line'=>1,
        ];

        $h = (object)[];
        $h->args = (array) $args;
        $h->argc = count($h->args);
        return [$h, $ctx];
    }

    function testHandlerVarInputAsKey()
    {
        $c = new Lang\Part\Core();
        $vars = ['foo'=>'yep'];
        list($h, $context) = $this->createContext($vars);
        $result = $c->handlers['var']($h, 'foo', $context);
        $this->assertEquals('yep', $result);
    }

    function testHandlerVarInputAsScope()
    {
        $c = new Lang\Part\Core();
        $vars = ['foo'=>'bar'];
        list($h, $context) = $this->createContext($vars, 'bar');
        $result = $c->handlers['var']($h, ['bar'=>'yep'], $context);
        $this->assertEquals('yep', $result);
    }

    function testHandlerVarArrayAccess()
    {
        $c = new Lang\Part\Core();
        $vars = new \ArrayObject(['foo'=>'yep']);
        list($h, $context) = $this->createContext($vars, 'foo');
        $result = $c->handlers['var']($h, '', $context);
        $this->assertEquals('yep', $result);
    }

    function testHandlerVarFailsWithInvalidScope()
    {
        $c = new Lang\Part\Core();
        $vars = (object)['foo'=>'yep'];
        list($h, $context) = $this->createContext($vars, 'foo');
        $this->setExpectedException("Tempe\Exception\Render", "Input scope was not an array or ArrayAccess at line 1");
        $c->handlers['var']($h, '', $context);
    }

    function testHandlerVarFromScope()
    {
        $c = new Lang\Part\Core();
        $vars = ['foo'=>'yep'];
        list($h, $context) = $this->createContext($vars, 'foo');
        $result = $c->handlers['var']($h, '', $context);
        $this->assertEquals('yep', $result);
    }

    function testValueHandlerOutputMissingContextKeyFails()
    {
        $c = new Lang\Part\Core();
        $vars = [];
        list($h, $context) = $this->createContext($vars, 'foo');

        $this->setExpectedException('Tempe\Exception\Render', "'var' could not find key 'foo' in context scope");
        $result = $c->handlers['var']($h, '', $context);
    }

    function testValueHandlerOutputMissingInputKeyFails()
    {
        $c = new Lang\Part\Core();
        $vars = [];
        list($h, $context) = $this->createContext($vars, 'foo');

        $this->setExpectedException('Tempe\Exception\Render', "'var' could not find key 'foo' in input scope");
        $result = $c->handlers['var']($h, [], $context);
    }
}
