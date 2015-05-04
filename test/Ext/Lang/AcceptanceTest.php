<?php
namespace Tempe\Test\Ext\Lang;

use Tempe\Ext;

class AcceptanceTest extends \PHPUnit_Framework_TestCase
{
    function testEachUnsetAllowed()
    {
        $tpl = "{{#each foo}}yep{{/each}}";
        $r = $this->getRenderer(new \Tempe\Ext\Lang(['allowUnsetKeys'=>true]));
        $vars = [];
        $this->assertEquals("", $r->render($tpl, $vars));
    }

    function testEachUnsetNotAllowed()
    {
        $tpl = "{{#each foo}}yep{{/each}}";
        $r = $this->getRenderer(new \Tempe\Ext\Lang(['allowUnsetKeys'=>false]));
        $vars = [];
        $this->setExpectedException("Tempe\RenderException", "Unknown variable foo");
        $r->render($tpl, $vars);
    }

    function testEachAssocArray()
    {
        $tpl = "{{#each foo}}{{var a1}} {{var a2}}\n{{/each}}";
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
        $tpl = "{{#each foo}}{{var 0}} {{var 1}}\n{{/each}}";
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
        $tpl = "{{#each foo}}{{var _idx_}}|{{var _num_}}) {{var _key_}} => {{var _value_}}\n{{/each}}";
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

    function testBlock()
    {
        $tpl = "{{#block hello}}world{{/block}}{{var hello}}";
        $expected = "world";
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEquals(['hello'=>'world'], $vars);
    }

    /** @depends testBlock */
    function testBlockNoKeyFilters()
    {
        $tpl = "{{#block | strtoupper}}world{{/block}}";
        $expected = "WORLD";
        $r = $this->getRenderer();
        $r->filters['strtoupper'] = 'strtoupper';
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEmpty($vars);
    }

    /** @depends testBlock */
    function testBlockKeyIgnoresFilters()
    {
        $tpl = "{{#block hello | strtoupper}}world {{var foo}}{{/block}}{{var hello}}";
        $expected = "world pants";
        $r = $this->getRenderer();
        $r->filters['strtoupper'] = 'strtoupper';
        $vars = ['foo'=>'pants'];
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEquals(['foo'=>'pants', 'hello'=>'world pants'], $vars);
    }

    /** @depends testBlock */
    function testBlockNoKeyNoFilters()
    {
        $tpl = "{{#block}}world{{/block}}";
        $expected = "world";
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEmpty($vars);
    }

    /** @depends testBlock */
    function testBlockOverwrites()
    {
        $tpl = "{{#block hello}}world{{/block}}{{#block hello}}pants{{/block}}{{var hello}}";
        $expected = "pants";
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEquals(['hello'=>'pants'], $vars);
    }

    /** @depends testBlock */
    function testBlockInsideEachDoesntEscape()
    {
        $tpl = "In: {{#each foo}}{{#block hello}}world{{/block}}{{var hello}}{{/each}}, Out: {{var hello}}";
        $expected = "In: worldworld, Out: ";
        $initialVars = $vars = [
            'foo'=>['a'=>'foo', 'b'=>'bar'],
        ];
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));

        // make sure the scope changes don't propagate upwards
        $this->assertEquals($initialVars, $vars);
    }

    function testPushAssocArray()
    {
        $tpl = "{{#push foo}}{{var bar}}{{/push}} {{var bar}}";
        $expected = "inner outer";
        $initialVars = $vars = [
            'foo'=>['bar'=>'inner'],
            'bar'=>'outer',
        ];
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));
        $this->assertEquals($initialVars, $vars);
    }

    function testPushNumericArray()
    {
        $tpl = "{{#push foo}}{{var 0}} {{var 1}}{{/push}}";
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
        $tpl = "{{#push foo}}yep{{/push}}";
        $r = $this->getRenderer(new \Tempe\Ext\Lang(['allowUnsetKeys'=>true]));
        $vars = [];
        $this->assertEquals("", $r->render($tpl, $vars));
    }

    function testPushUnsetNotAllowed()
    {
        $tpl = "{{#push foo}}yep{{/push}}";
        $r = $this->getRenderer(new \Tempe\Ext\Lang(['allowUnsetKeys'=>false]));
        $vars = [];
        $this->setExpectedException("Tempe\RenderException", "Unknown variable foo");
        $r->render($tpl, $vars);
    }

    private function getRenderer($ext=null)
    {
        return new \Tempe\Renderer([$ext ?: new Ext\Lang]);
    }
}
