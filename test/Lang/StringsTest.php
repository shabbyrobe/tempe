<?php
namespace Tempe\Test\Lang;

class StringsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dataStringFunctions
     */
    public function testStringFunction($function, $in, $expected)
    {
        $lang = new \Tempe\Lang\Basic();
        $lang->addPart(new \Tempe\Lang\Part\Strings());
        $lang->handlers['in'] = function() use ($in) { return $in; };

        $renderer = new \Tempe\Renderer($lang);
        $vars = ['in'=>$in];
        $out = $renderer->render("{= in | $function }", $vars);
        $this->assertEquals($expected, $out);
    }

    function dataStringFunctions()
    {
        return [
            ['upper', 'hello', 'HELLO'],
            ['lower', 'HELLO', 'hello'],
            ['ucfirst', 'hello', 'Hello'],
            ['lcfirst', 'HELLO', 'hELLO'],
            ['ucwords', 'hello world', 'Hello World'],
            ['trim', ' a ', 'a'],
            ['ltrim', ' a ', 'a '],
            ['rtrim', ' a ', ' a'],
            ['nl2br', "a\nb", "a<br />\nb"],
            ['nl2spc', "a\nb", "a b"],
        ];
    }
}
