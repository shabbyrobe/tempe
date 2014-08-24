<?php
namespace Tempe\Test\Ext;

use Tempe\Ext;
use Tempe\Renderer as R;

class LangTest extends \PHPUnit_Framework_TestCase
{
    function testConstructInvalidOptionsFails()
    {
        $this->setExpectedException('InvalidArgumentException');
        $c = new Ext\Lang(['helloHowAreYou'=>'yep', 'iAmWellThankYou'=>'indeedy']);
    }

    function testConstructEnablesAllBlocksByDefault()
    {
        $c = new Ext\Lang();
        $this->assertEquals(['if', 'not', 'each', 'block', 'push'], array_keys($c->blockHandlers));
    }

    function testConstructDisableSpecificBlocks()
    {
        $c = new Ext\Lang(['blocks'=>['each'=>false, 'block'=>false, 'push'=>false]]);
        $this->assertEquals(['if', 'not'], array_keys($c->blockHandlers));
    }

    function testConstructDisablesAllBlocksWhenOptionFalse()
    {
        $c = new Ext\Lang(['blocks'=>false]);
        $this->assertEquals([], array_keys($c->blockHandlers));
    }

    function testValueHandlerOutput()
    {
        $c = new Ext\Lang();
        $vars = ['foo'=>'yep'];
        $result = $c->valueHandlers['=']($vars, 'foo');
        $this->assertEquals('yep', $result);
    }

    function testValueHandlerOutputMissingKeyDefault()
    {
        $c = new Ext\Lang();
        $vars = [];
        $result = $c->valueHandlers['=']($vars, 'foo');
        $this->assertEquals('', $result);
    }

    function testValueHandlerOutputMissingKeyFailsWhenDisallowed()
    {
        $c = new Ext\Lang(['allowUnsetKeys'=>false]);
        $vars = [];
        $this->setExpectedException('Tempe\RenderException', 'Unknown variable foo');
        $result = $c->valueHandlers['=']($vars, 'foo');
    }
}
