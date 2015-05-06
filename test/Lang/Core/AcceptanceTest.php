<?php
namespace Tempe\Test\Ext\Lang;

use Tempe\Ext;

class AcceptanceTest extends \PHPUnit_Framework_TestCase
{
    function testBlockSetFirstNulls()
    {
        $tpl = "{{#set hello}}world{{/}}{{get hello}}";
        $expected = "";
        $r = $this->getRenderer();
        $vars = ['hello'=>'WOO'];
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEquals(['hello'=>null], $vars);
    }

    function testValueSetFailsIfNotLast()
    {
        $tpl = "{{set hello | upper}}";
        $expected = "WORLD";
        $r = $this->getRenderer();
        $this->setExpectedException('Tempe\Exception\Check', "Handlers must not follow 'set' in a chain");
        $r->render($tpl, $vars);
    }

    function testBlockSetFailsIfNotLast()
    {
        $tpl = "{{#set hello | upper}}world{{/}}";
        $expected = "WORLD";
        $r = $this->getRenderer();
        $this->setExpectedException('Tempe\Exception\Check', "Handlers must not follow 'set' in a chain");
        $r->render($tpl, $vars);
    }

    function testBlockSetChained()
    {
        $tpl = "{{# show | upper | set hello }}world{{/}}{{ get hello }}";
        $expected = "WORLD";
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEquals(['hello'=>'WORLD'], $vars);
    }

    /** @depends testBlockSetChained */
    function testBlockSetInsideEachDoesntHoist()
    {
        $tpl = "In: {{#each foo}}{{#show | set hello}}world{{/}}{{get hello}}{{/}}, Out: {{get hello }}";
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
        $tpl = "{{#push foo}}{{get bar }}{{/}} {{get bar }}";
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
        $tpl = "{{# push foo}}{{# push bar }}{{get baz }}{{/}}{{/}} {{ get baz }}";
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
        $tpl = "{{#push foo}}{{get 0}} {{get 1}}{{/}}";
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
        $tpl = "{{get foo | as html}}";
        $vars = ['foo'=>'&&'];
        $r = $this->getRenderer();
        $this->assertEquals("&amp;&amp;", $r->render($tpl, $vars));
    }

    function testValueMultiAs()
    {
        $tpl = "{{get foo | as html | as urlquery}}";
        $vars = ['foo'=>'&amp;'];
        $r = $this->getRenderer();
        $this->assertEquals("%26amp%3Bamp%3B", $r->render($tpl, $vars));
    }

    function testAsCannotBeFirst()
    {
        $tpl = "{{as html}}";
        $vars = [];
        $r = $this->getRenderer();
        $this->setExpectedException('Tempe\Exception\Check', "Handler 'as' must not be first at line 1");
        $r->render($tpl, $vars);
    }

    function testBlockAs()
    {
        $tpl = "{{#show | as html}}{{get foo}}{{/}}";
        $vars = ['foo'=>'&&'];
        $r = $this->getRenderer();
        $this->assertEquals("&amp;&amp;", $r->render($tpl, $vars));
    }

    private function getRenderer()
    {
        return new \Tempe\Renderer(\Tempe\Lang\Factory::createBasic());
    }
}
