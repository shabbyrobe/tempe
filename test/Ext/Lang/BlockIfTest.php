<?php
namespace Tempe\Test\Ext\Lang;

use Tempe\Ext;

class BlockIfTest extends \PHPUnit_Framework_TestCase
{
    function setUp()
    {
        $this->renderer = $this->getMockBuilder('Tempe\Renderer')->setMethods(['render', 'renderTree'])->getMock();
    }

    /** 
     * @dataProvider dataTruthy
     */
    function testIfTrueShow($truthy)
    {
        $this->runShownBlockTest('if', ['foo'=>$truthy], 'foo');
    }

    /** 
     * @dataProvider dataFalsey
     */
    function testNotFalseShow($falsey)
    {
        $this->runShownBlockTest('not', ['foo'=>$falsey], 'foo');
    }

    /** 
     * @dataProvider dataFalsey
     */
    function testIfFalseHide($falsey)
    {
        $this->runHiddenBlockTest('if', ['foo'=>$falsey], 'foo');
    }

    /** 
     * @dataProvider dataTruthy
     */
    function testNotTrueHide($truthy)
    {
        $this->runHiddenBlockTest('not', ['foo'=>$truthy], 'foo');
    }

    function testIfUnsetHide()
    {
        $this->runHiddenBlockTest('if', [], 'foo');
    }

    function testIfUnsetHideWhenUnsetDisallowed()
    {
        $this->runHiddenBlockTest('if', [], 'foo', new Ext\Lang(['allowUnsetKeys'=>false]));
    }

    function testNotUnsetShow()
    {
        $this->runShownBlockTest('not', [], 'foo');
    }

    function testNotUnsetShowWhenUnsetDisallowed()
    {
        $this->runShownBlockTest('not', [], 'foo', new Ext\Lang(['allowUnsetKeys'=>false]));
    }

    function runShownBlockTest($handler, $vars, $key, $ext=null)
    {
        $contents = new \stdClass;
        $return = 'yes';
        $this->renderer->addExtension($ext ?: new Ext\Lang);
        $this->renderer->expects($this->once())
            ->method('renderTree')
            ->with($contents)
            ->will($this->returnValue($return))
        ;
        $this->assertEquals($return, $this->renderer->blockHandlers[$handler]($vars, $key, $contents));
    }

    function runHiddenBlockTest($handler, $vars, $key, $ext=null)
    {
        $contents = new \stdClass;
        $this->renderer->addExtension($ext ?: new Ext\Lang);
        $this->renderer->expects($this->never())->method('renderTree');
        $this->assertEmpty($this->renderer->blockHandlers[$handler]($vars, $key, $contents));
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
            [0],
            ["0"],
            [[]],
            [null],
        ];
    }
}
