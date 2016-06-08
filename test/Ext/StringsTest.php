<?php
namespace Tempe\Test\Ext;

class StringsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dataForFilter
     */
    function testFilter($filter, $in, $out)
    {
        $renderer = new \Tempe\Renderer();
        $renderer->addExtension(new \Tempe\Ext\Strings);
        $renderer->valueHandlers['in'] = function() use ($in) {
            return $in;
        };
        $this->assertEquals($out, $renderer->render("{{in|$filter}}", $vars));
    }

    function dataForFilter()
    {
        return [
            ['lower', 'YEP', 'yep'],
            ['upper', 'yep', 'YEP'],
        ];
    }
}
