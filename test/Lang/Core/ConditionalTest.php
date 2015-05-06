<?php
namespace Tempe\Test\Lang\Core;

class ConditionalTest extends \PHPUnit_Framework_TestCase
{
    function setUp()
    {
        $this->renderer = new \Tempe\Renderer(\Tempe\Lang\Factory::createBasic());
    }

    /** 
     * @dataProvider dataTruthy
     */
    function testIfTrueShow($truthy)
    {
        $vars = ['foo'=>$truthy];
        $out = $this->renderer->render('{{# get foo | show }}shown{{/}}', $vars);
        $this->assertEquals('shown', $out);
    }

    /** 
     * @dataProvider dataFalsey
     */
    function testIfFalseHide($falsey)
    {
        $vars = ['foo'=>$falsey];
        $out = $this->renderer->render('{{# get foo | show }}shown{{/}}', $vars);
        $this->assertEquals('', $out);
    }

    /** 
     * @dataProvider dataFalsey
     */
    function testNotFalseShow($falsey)
    {
        $vars = ['foo'=>$falsey];
        $out = $this->renderer->render('{{# get foo | not | show }}shown{{/}}', $vars);
        $this->assertEquals('shown', $out);
    }

    /** 
     * @dataProvider dataTruthy
     */
    function testNotTrueHide($truthy)
    {
        $vars = ['foo'=>$truthy];
        $out = $this->renderer->render('{{# get foo | not | show }}shown{{/}}', $vars);
        $this->assertEquals('', $out);
    }

    /**
     * @dataProvider dataEqVar
     */
    function testEqVarShow($l, $r, $yep=true)
    {
        $vars = ['foo'=>$l, 'bar'=>$r];
        $out = $this->renderer->render('{{# get foo | eqvar bar | show }}shown{{/}}', $vars);
        $this->assertEquals($yep ? 'shown' : '', $out);
    }

    function dataEqVar()
    {
        return [
            ['a', 'a', true],
            ['a', 'b', false],
            [[], false, false],
            [false, false, true],
            [null, null, true],
            [['a'=>'a'], ['a'=>'a']],
            [(object)['a'=>'a'], (object)['a'=>'a']],
        ];
    }

    /**
     * @dataProvider dataEqVal
     */
    function testEqValShow($l, $r, $yep=true)
    {
        $vars = ['foo'=>$l];
        $out = $this->renderer->render("{{# get foo | eqval $r | show }}shown{{/}}", $vars);
        $this->assertEquals($yep ? 'shown' : '', $out);
    }

    function dataEqVal()
    {
        return [
            ['a', 'a', true],
            ['a', 'b', false],
            [1, '1', true],
            ['', '1', false],
        ];
    }

    function testEqValShortCircuits()
    {
        $vars = ['foo'=>'a'];
        $this->renderer->lang->handlers['quack'] = function () { return 'quack'; };
        $out = $this->renderer->render("{{# get foo | eqval b | quack }}{{/}}", $vars);
        $this->assertEquals('', $out);
    }

    function testEqVarShortCircuits()
    {
        $vars = ['foo'=>'a', 'bar'=>'b'];
        $this->renderer->lang->handlers['quack'] = function () { return 'quack'; };
        $out = $this->renderer->render("{{# get foo | eqvar bar | quack }}{{/}}", $vars);
        $this->assertEquals('', $out);
    }

    function testEqVarUnset()
    {
        $vars = ['foo'=>'a'];
        $this->setExpectedException('Tempe\Exception\Render', "'eqvar' could not find key 'bar' in scope at line 1");
        $this->renderer->render("{{# get foo | eqvar bar | show }}{{/}}", $vars);
    }

    function dataTruthy()
    {
        return [
            [true],
            [1.123],
            [1],
            ["1"],
            [['yes']],
            [[false]],
            [['a'=>'b']],
            [new \stdClass],
        ];
    }

    function dataFalsey()
    {
        return [
            [false],
            [''],
            [0],
            ["0"],
            [[]],
            [null],
        ];
    }
}
