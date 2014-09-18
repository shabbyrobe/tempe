<?php
namespace Tempe\Test;

use Tempe\Renderer;

class RendererHelperTest extends \PHPUnit_Framework_TestCase
{
    function testLangOptionsPassedBasic()
    {
        $this->setExpectedException('InvalidArgumentException', 'Unknown options: intentionallyDodgyKey');
        $r = Renderer::createBasic(array('lang'=>[
            'intentionallyDodgyKey'=>true
        ]));
    }

    function testLangOptionsPassedBasicWeb()
    {
        $this->setExpectedException('InvalidArgumentException', 'Unknown options: intentionallyDodgyKey');
        $r = Renderer::createBasicWeb(array('lang'=>[
            'intentionallyDodgyKey'=>true
        ]));
    }
}
