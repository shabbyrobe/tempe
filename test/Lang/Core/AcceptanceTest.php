<?php
namespace Tempe\Test\Ext\Lang;

use Tempe\Ext;

class AcceptanceTest extends \PHPUnit_Framework_TestCase
{
    function testBlockSetFirstCaptures()
    {
        $tpl = "{{#set hello}}world{{/}}{{var hello}}";
        $expected = "world";
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEquals(['hello'=>'world'], $vars);
    }

    function testValueSetFailsIfNotLast()
    {
        $tpl = "{{set hello | upper}}";
        $expected = "WORLD";
        $r = $this->getRenderer();
        $this->setExpectedException('Tempe\Exception\Check', "Handlers may not follow 'set' in a chain");
        $r->render($tpl, $vars);
    }

    function testBlockSetFailsIfNotLast()
    {
        $tpl = "{{#set hello | upper}}world{{/}}";
        $expected = "WORLD";
        $r = $this->getRenderer();
        $this->setExpectedException('Tempe\Exception\Check', "Handlers may not follow 'set' in a chain");
        $r->render($tpl, $vars);
    }

    /** @depends testBlockSetFirstCaptures */
    function testBlockSetFirstOverwrites()
    {
        $tpl = "{{#set hello}}world{{/}}{{#set hello}}pants{{/}}{{var hello}}";
        $expected = "pants";
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEquals(['hello'=>'pants'], $vars);
    }

    function testBlockSetChained()
    {
        $tpl = "{{# show | upper | set hello }}world{{/}}{{ var hello }}";
        $expected = "WORLD";
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEquals(['hello'=>'WORLD'], $vars);
    }

    /** @depends testBlockSetFirstCaptures */
    function testBlockSetFirstInsideEachDoesntHoist()
    {
        $tpl = "In: {{#each foo}}{{#set hello}}world{{/}}{{var hello}}{{/}}, Out: {{var hello }}";
        $expected = "In: worldworld, Out: yep";
        $initialVars = $vars = [
            'hello'=>'yep', 'foo'=>['a'=>'foo', 'b'=>'bar'],
        ];
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));

        // make sure the scope changes don't propagate upwards
        $this->assertEquals($initialVars, $vars);
    }

    function testPushAssocArray()
    {
        $tpl = "{{#push foo}}{{var bar }}{{/}} {{var bar }}";
        $expected = "inner outer";
        $initialVars = $vars = [
            'foo'=>['bar'=>'inner'],
            'bar'=>'outer',
        ];
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEquals($initialVars, $vars);
    }

    function testPushNestedHoists()
    {
        $tpl = "{{# push foo}}{{# push bar }}{{var baz }}{{/}}{{/}} {{ var baz }}";
        $expected = "inner outer";
        $initialVars = $vars = [
            'foo'=>['bar'=>['baz'=>'inner']],
            'baz'=>'outer',
        ];
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEquals($initialVars, $vars);
    }

    function testPushNumericArray()
    {
        $tpl = "{{#push foo}}{{var 0}} {{var 1}}{{/}}";
        $expected = "bar baz";
        $initialVars = $vars = [
            'foo'=>['bar', 'baz'],
        ];
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEquals($initialVars, $vars);
    }

    function testPushUnsetAllowed()
    {
        $tpl = "{{#push foo}}yep{{/}}";
        $r = $this->getRenderer();
        $vars = [];
        $this->assertEquals("yep", $r->render($tpl, $vars));
    }

    function testValueAs()
    {
        $tpl = "{{var foo | as html}}";
        $vars = ['foo'=>'&&'];
        $r = $this->getRenderer();
        $this->assertEquals("&amp;&amp;", $r->render($tpl, $vars));
    }

    function testAsCannotBeFirst()
    {
        $tpl = "{{as html}}";
        $vars = [];
        $r = $this->getRenderer();
        $this->setExpectedException('Tempe\Exception\Check', "Handler 'as' may not be first at line 1");
        $r->render($tpl, $vars);
    }

    function testBlockAs()
    {
        $tpl = "{{#show | as html}}{{var foo}}{{/}}";
        $vars = ['foo'=>'&&'];
        $r = $this->getRenderer();
        $this->assertEquals("&amp;&amp;", $r->render($tpl, $vars));
    }

    private function getRenderer()
    {
        return new \Tempe\Renderer(\Tempe\Lang\Factory::createBasic());
    }
}
