<?php
namespace Tempe\Test\Ext;

use org\bovigo\vfs;

class PartialTest extends \PHPUnit_Framework_TestCase
{
    function setUp()
    {
        vfs\vfsStreamWrapper::setRoot(new vfs\vfsStreamDirectory('test'));

        $partial = new \Tempe\Lang\Part\Partial(['paths'=>['parts'=>"vfs://test"]]);
        $core = new \Tempe\Lang\Part\Core;
        $lang = (new \Tempe\Lang\Basic())->addPart($partial);
        $lang->handlers['get'] = $core->handlers['get'];
        $this->renderer = new \Tempe\Renderer($lang);

        $count = 0;
        $v = function() use (&$count) { return $count++; };
        $lang->handlers['test'] = $v;
    }

    /**
     * @dataProvider dataTraversalFails
     */
    function testTraversalFails($handler, $file)
    {
        $tpl = "{= $handler $file }";
        $this->setExpectedException("Tempe\Exception\Check", "Invalid file $file");
        $out = $this->renderer->render($tpl);
    }

    function dataTraversalFails()
    {
        return [
            ['tpl', 'alias/../../naughty'],
            ['incl', 'alias/../../naughty'],
        ];
    }

    function testNoAliasFails()
    {
        $tpl = "{= tpl file }";
        $this->setExpectedException("Tempe\Exception\Check", "Missing alias in file");
        $out = $this->renderer->render($tpl);
    }

    function testUnknownAliasFails()
    {
        $tpl = "{= tpl alias/file }";
        $this->setExpectedException("Tempe\Exception\Check", "Unknown alias alias");
        $out = $this->renderer->render($tpl);
    }

    function testUnknownFileFails()
    {
        $tpl = "{= tpl parts/file }";
        $this->setExpectedException("Tempe\Exception\Check", "Could not load vfs://test/file");
        $out = $this->renderer->render($tpl);
    }

    function testTpl()
    {
        $tpl = "-{= tpl parts/test.tpl }-";
        file_put_contents("vfs://test/test.tpl", "{= test }");
        $out = $this->renderer->render($tpl);
        $this->assertEquals("-0-", $out);
    }

    function testTplTwice()
    {
        $tpl = "-{= tpl parts/test.tpl }{= tpl parts/test.tpl }-";
        file_put_contents("vfs://test/test.tpl", "{= test }");
        $out = $this->renderer->render($tpl);
        $this->assertEquals("-01-", $out);
    }

    function testTplNested()
    {
        $tpl = "-{= tpl parts/child1.tpl }-";
        file_put_contents("vfs://test/child1.tpl", "{= tpl parts/child2.tpl }");
        file_put_contents("vfs://test/child2.tpl", "{= test }");
        $out = $this->renderer->render($tpl);
        $this->assertEquals("-0-", $out);
    }

    function testTplGet()
    {
        $tpl = "-{= get file | tpl }-";
        file_put_contents("vfs://test/test.tpl", "{= test }");
        $vars = ['file'=>'parts/test.tpl'];
        $out = $this->renderer->render($tpl, $vars);
        $this->assertEquals("-0-", $out);
    }

    function testIncl()
    {
        $tpl = "-{= incl parts/test.txt }-";
        file_put_contents("vfs://test/test.txt", "{= test }");
        $out = $this->renderer->render($tpl);
        $this->assertEquals("-{= test }-", $out);
    }

    function testInclTwice()
    {
        $tpl = "-{= incl parts/test.txt }{= incl parts/test.txt }-";
        file_put_contents("vfs://test/test.txt", "{= test }");
        $out = $this->renderer->render($tpl);
        $this->assertEquals("-{= test }{= test }-", $out);
    }

    function testInclVar()
    {
        $tpl = "-{= get file | incl }-";
        file_put_contents("vfs://test/test.txt", "{= test }");
        $vars = ['file'=>'parts/test.txt'];
        $out = $this->renderer->render($tpl, $vars);
        $this->assertEquals("-{= test }-", $out);
    }
}
