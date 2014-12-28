<?php
namespace Tempe\Test\Ext\Lang;

use Tempe\Ext;

class AcceptanceTest extends \PHPUnit_Framework_TestCase
{
    function testEachUnsetNotAllowed()
    {
        $tpl = "{{# each foo }}yep{{/}}";
        $r = $this->getRenderer();
        $vars = [];
        $this->setExpectedException("Tempe\Exception\Render", "Unknown variable foo");
        $r->render($tpl, $vars);
    }

    function testEachAssocArray()
    {
        $tpl = "{{#each foo}}{{ var a1 }} {{var a2}}\n{{/}}";
        $expected = "foo bar\nbaz qux\n";
        $initialVars = $vars = [
            'foo'=>[
                ['a1'=>'foo', 'a2'=>'bar'],
                ['a1'=>'baz', 'a2'=>'qux'],
            ],
        ];
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));

        // make sure the scope changes don't propagate upwards
        $this->assertEquals($initialVars, $vars);
    }

    function testEachNumericArray()
    {
        $tpl = "{{#each foo}}{{var 0}} {{var 1}}\n{{/}}";
        $expected = "foo bar\nbaz qux\n";
        $initialVars = $vars = [
            'foo'=>[
                ['foo', 'bar'],
                ['baz', 'qux'],
            ],
        ];
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));

        // make sure the scope changes don't propagate upwards
        $this->assertEquals($initialVars, $vars);
    }

    function testEachMetaVars()
    {
        $tpl = "{{#each foo}}{{var _idx_}}|{{var _num_ }}) {{var _key_}} => {{var _value_}}\n{{/}}";
        $expected = "0|1) a => foo\n1|2) b => bar\n2|3) c => baz\n";
        $initialVars = $vars = [
            'foo'=>[
                'a'=>'foo',
                'b'=>'bar',
                'c'=>'baz',
            ],
        ];
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));

        // make sure the scope changes don't propagate upwards
        $this->assertEquals($initialVars, $vars);
    }

    function testSetCapture()
    {
        $tpl = "{{#set hello}}world{{/}}{{var hello}}";
        $expected = "world";
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEquals(['hello'=>'world'], $vars);
    }

    function testSetCaptureFailsIfNotLast()
    {
        $tpl = "{{#set hello | upper}}world{{/}}";
        $expected = "WORLD";
        $r = $this->getRenderer();
        $this->setExpectedException('Tempe\Exception\Check', "Handlers may not follow 'set' in a chain");
        $r->render($tpl, $vars);
    }

    /** @depends testSetCapture */
    function testSetCaptureOverwrites()
    {
        $tpl = "{{#set hello}}world{{/}}{{#set hello}}pants{{/}}{{var hello}}";
        $expected = "pants";
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEquals(['hello'=>'pants'], $vars);
    }

    /** @depends testSetCapture */
    function testSetCaptureInsideEachDoesntHoist()
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
        $r = $this->getRenderer(new \Tempe\Lang\Part\Core());
        $vars = [];
        $this->assertEquals("yep", $r->render($tpl, $vars));
    }

    private function getRenderer($ext=null)
    {
        return new \Tempe\Renderer(\Tempe\Lang\Factory::createBasic());
    }
}
