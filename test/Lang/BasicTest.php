<?php
namespace Tempe\Test\Lang;

use Tempe\Renderer;
use Tempe\Parser;
use Tempe\Lang\Basic;

class BasicTest extends \PHPUnit_Framework_TestCase
{
    function testCheckArgMin()
    {
        $parser = new Parser(new Basic(
            ['h'=>function() {}], ['h'=>['argMin'=>2]]
        ));
        $this->setExpectedException('Tempe\Exception\Check', "Handler 'h' min args 2, found 1 at line 1");
        $parser->parse('{{ h arg }}');
    }

    function testCheckArgMax()
    {
        $parser = new Parser(new Basic(
            ['h'=>function() {}], ['h'=>['argMax'=>1]]
        ));
        $this->setExpectedException('Tempe\Exception\Check', "Handler 'h' max args 1, found 2 at line 1");
        $parser->parse('{{ h arg1 arg2 }}');
    }

    function testCheckArgcTooMany()
    {
        $parser = new Parser(new Basic(
            ['h'=>function() {}], ['h'=>['argc'=>2]]
        ));
        $this->setExpectedException('Tempe\Exception\Check', "Handler 'h' expected 2 arg(s), found 3 at line 1");
        $parser->parse('{{ h arg1 arg2 arg3 }}');
    }

    function testCheckArgcTooFew()
    {
        $parser = new Parser(new Basic(
            ['h'=>function() {}], ['h'=>['argc'=>2]]
        ));
        $this->setExpectedException('Tempe\Exception\Check', "Handler 'h' expected 2 arg(s), found 1 at line 1");
        $parser->parse('{{ h arg1 }}');
    }

    function testCheckPreventValue()
    {
        $parser = new Parser(new Basic(
            ['h'=>function() { return 'yep'; }], ['h'=>['allowValue'=>false]]
        ));
        $this->setExpectedException('Tempe\Exception\Check', "Handler 'h' can not be used with a value tag at line 1");
        $parser->parse('{{ h arg1 }}');
    }

    function testCheckPreventBlock()
    {
        $parser = new Parser(new Basic(
            ['h'=>function() { return 'yep'; }], ['h'=>['allowBlock'=>false]]
        ));
        $this->setExpectedException('Tempe\Exception\Check', "Handler 'h' can not be used with a block tag at line 1");
        $parser->parse('{{# h arg1 }}{{/}}');
    }

    function testCheckLast()
    {
        $h = ['foo'=>function() {}, 'h'=>function() {}];
        $parser = new Parser(new Basic($h, ['h'=>['last'=>true]]));
        $this->assertNotEmpty($parser->parse('{{ foo | h }}'));
        $this->assertNotEmpty($parser->parse('{{ foo | foo | h }}'));
        $this->assertNotEmpty($parser->parse('{{ h }}'));

        $this->setExpectedException('Tempe\Exception\Check', "Handlers must not follow 'h' in a chain at line 1");
        $parser->parse('{{ h | foo }}');
    }

    function testCheckNotChainable()
    {
        $h = ['h'=>function() {}];
        $parser = new Parser(new Basic($h, ['h'=>['chainable'=>false]]));
        $this->setExpectedException('Tempe\Exception\Check', "Handler 'h' is not chainable at line 1");
        $parser->parse('{{ h | h }}');
    }

    function testCheckNotFirst()
    {
        $h = ['foo'=>function() {}, 'h'=>function() {}];
        $parser = new Parser(new Basic($h, ['h'=>['notFirst'=>false]]));

        $this->assertNotEmpty($parser->parse('{{ foo | h }}'));
        $this->assertNotEmpty($parser->parse('{{ foo | h | h }}'));
        $this->assertNotEmpty($parser->parse('{{# foo | h | h }}{{/}}'));

        $this->setExpectedException('Tempe\Exception\Check', "Handler 'h' must not be first at line 1");
        $parser->parse('{{ h | foo }}');
    }

    function testCheckCallbackOkOnTrue()
    {
        $h = ['yep'=>function() {}];
        $r = ['yep'=>['check'=>function() { return true; }]];
        $parser = new Parser(new Basic($h, $r));
        $this->assertNotEmpty($parser->parse('{{ yep }}'));
    }

    function testCheckCallbackFailsOnFalse()
    {
        $h = ['nup'=>function() {}];
        $r = ['nup'=>['check'=>function() { return false; }]];
        $parser = new Parser(new Basic($h, $r));
        $this->setExpectedException('Tempe\Exception\Check', "Handler 'nup' check failed at line 1");
        $parser->parse('{{ nup }}');
    }

    function testCheckCallbackFailsOnException()
    {
        $h = ['nup'=>function() {}];
        $r = ['nup'=>['check'=>function($handler, $node, $chainPos) {
            throw new \Tempe\Exception\Check("NUP!", $node->line);
        }]];
        $parser = new Parser(new Basic($h, $r));
        $this->setExpectedException('Tempe\Exception\Check', "NUP!");
        $parser->parse('{{ nup }}');
    }

    function testHandlerUnknown()
    {
        $lang = new \Tempe\Lang\Basic([]);
        $parser = new \Tempe\Parser;
        $renderer = new \Tempe\Renderer($lang);
        $this->setExpectedException("Tempe\Exception\Render", "Handler 'nope' not found at line 1");
        $renderer->renderTree($parser->parse("{{nope}}"));
    }
}
