<?php
namespace Tempe\Test;

class HelperTest extends \PHPUnit_Framework_TestCase
{
    function testNodeNameFromType()
    {
        $rc = new \ReflectionClass('Tempe\Renderer');
        foreach ($rc->getConstants() as $k=>$v) {
            if (strpos($k, 'P_')===0) {
                $name = \Tempe\Helper::nodeName(constant('Tempe\Renderer::'.$k));
                $this->assertEquals($k, $name);
            }
        }
    }

    function testNodeNameFromNode()
    {
        $node = (object)['type'=>\Tempe\Renderer::P_VALUE];
        $name = \Tempe\Helper::nodeName($node);
        $this->assertEquals('P_VALUE', $name);
    }

    function testNodeNameUnknown()
    {
        $name = \Tempe\Helper::nodeName(9999999);
        $this->assertEquals('UNKNOWN', $name);
    }
}
