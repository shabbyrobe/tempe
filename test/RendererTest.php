<?php
namespace Tempe\Test;

use Tempe\Parser;
use Tempe\Renderer;

class RendererTest extends \PHPUnit_Framework_TestCase
{
    function setUp()
    {
        $this->parser = new Parser();
    }

    function testRenderValueHandlerNoKeyNoFilter()
    {
        $tpl = '{{h}}';
        $tree = $this->parser->parse($tpl);
        
        $renderer = new Renderer;
        $renderer->valueHandlers['h'] = function() {
            return 'bingo';
        };
        $this->assertEquals('bingo', $renderer->render($tpl));
    }

    /**
     * @dataProvider dataStringable
     */
    function testRenderValueHandlerStringableOutput($value, $expected)
    {
        $tpl = '{{h}}';
        $tree = $this->parser->parse($tpl);
        
        $renderer = new Renderer;
        $renderer->valueHandlers['h'] = function() use ($value) {
            return $value;
        };
        $this->assertEquals($expected, $renderer->render($tpl));
    }

    function dataStringable()
    {
        return [
            ['foo', 'foo'],
            ['0', '0'], 
            [1, '1'],
            [0, '0'], 
            [false, ''],
            [true, '1'],
        ];
    }

    function testRenderValueHandlerKeyNoFilter()
    {
        $tpl = '{{h key}}';
        $tree = $this->parser->parse($tpl);
        
        $renderer = new Renderer;
        $renderer->valueHandlers['h'] = function($scope, $key) {
            return 'bingo '.$key;
        };
        $this->assertEquals('bingo key', $renderer->render($tpl));
    }

    function testRenderValueHandlerKeyFilter()
    {
        $tpl = '{{h key|f1}}';
        $renderer = new Renderer;
        $renderer->valueHandlers['h'] = function($scope, $key) {
            return 'bingo '.$key;
        };
        $renderer->filters['f1'] = 'strtoupper';
        $this->assertEquals('BINGO KEY', $renderer->render($tpl));
    }

    function testRenderValueHandlerKeyMultipleFilters()
    {
        $tpl = '{{h key|f1|f2}}';
        $renderer = new Renderer;
        $renderer->valueHandlers['h'] = function($scope, $key) {
            return 'bingo '.$key;
        };
        $renderer->filters['f1'] = 'strtoupper';
        $renderer->filters['f2'] = 'lcfirst';
        $this->assertEquals('bINGO KEY', $renderer->render($tpl));
    }

    function testRenderValueHandlerCallableFilter()
    {
        $tpl = '{{h key|f}}';
        $renderer = new Renderer;
        $renderer->valueHandlers['h'] = function($scope, $key) {};

        $called = false;
        $renderer->filters['f'] = function($value) use (&$called) { $called = true; };
        $renderer->render($tpl);
        $this->assertTrue($called);
    }

    function testRenderValueHandlerClassFilter()
    {
        $tpl = '{{h key|f.x}}';
        $renderer = new Renderer;
        $renderer->valueHandlers['h'] = function($scope, $key) { return "value"; };
        
        $renderer->filters['f'] = new TestFilter;
        $this->assertEquals('x(value)', $renderer->render($tpl));
    }

    function testRenderValueHandlerClassFilterDoesntNest()
    {
        $tpl = '{{h key|f.x.x}}';
        $renderer = new Renderer;
        $renderer->valueHandlers['h'] = function($scope, $key) { return "value"; };

        $renderer->filters['f'] = new TestFilter;
        $this->assertEquals('x.x(value)', $renderer->render($tpl));
    }

    function testRenderBlockHandlerNoKeyNoFilter()
    {
        $tpl = '{{#h}}{{/h}}';
        $tree = $this->parser->parse($tpl);
        
        $renderer = new Renderer;
        $renderer->blockHandlers['h'] = function() {
            return 'bingo';
        };
        $this->assertEquals('bingo', $renderer->render($tpl));
    }

    function testRenderBlockHandlerKeyNoFilter()
    {
        $tpl = '{{#h key}}{{/h}}';
        $tree = $this->parser->parse($tpl);
        
        $renderer = new Renderer;
        $renderer->blockHandlers['h'] = function($scope, $key) {
            return 'bingo '.$key;
        };
        $this->assertEquals('bingo key', $renderer->render($tpl));
    }

    function testRenderBlockHandlerKeyFilter()
    {
        $tpl = '{{#h key|f1}}{{/h}}';
        $renderer = new Renderer;
        $renderer->blockHandlers['h'] = function($scope, $key) {
            return 'bingo '.$key;
        };
        $renderer->filters['f1'] = 'strtoupper';
        $this->assertEquals('BINGO KEY', $renderer->render($tpl));
    }

    function testRenderBlockHandlerKeyMultipleFilters()
    {
        $tpl = '{{#h key|f1|f2}}{{/h}}';
        $renderer = new Renderer;
        $renderer->blockHandlers['h'] = function($scope, $key) {
            return 'bingo '.$key;
        };
        $renderer->filters['f1'] = 'strtoupper';
        $renderer->filters['f2'] = 'lcfirst';
        $this->assertEquals('bINGO KEY', $renderer->render($tpl));
    }

    function testRenderBlockHandlerCallableFilter()
    {
        $tpl = '{{#h key|f}}{{/h}}';
        $renderer = new Renderer;
        $renderer->blockHandlers['h'] = function($scope, $key) {};

        $called = false;
        $renderer->filters['f'] = function($value) use (&$called) { $called = true; };
        $renderer->render($tpl);
        $this->assertTrue($called);
    }

    function testRenderBlockHandlerClassFilter()
    {
        $tpl = '{{#h key|f.x}}{{/h}}';
        $renderer = new Renderer;
        $renderer->blockHandlers['h'] = function($scope, $key) { return "value"; };
        
        $renderer->filters['f'] = new TestFilter;
        $this->assertEquals('x(value)', $renderer->render($tpl));
    }

    function testRenderBlockHandlerClassFilterDoesntNest()
    {
        $tpl = '{{#h key|f.x.x}}{{/h}}';
        $renderer = new Renderer;
        $renderer->blockHandlers['h'] = function($scope, $key) { return "value"; };

        $renderer->filters['f'] = new TestFilter;
        $this->assertEquals('x.x(value)', $renderer->render($tpl));
    }

    function testRenderBlockCapturesContents()
    {
        $tpl = '{{#h}}foo{{/h}}';
        $tree = $this->parser->parse($tpl);
        $renderer = new Renderer;

        $renderer->blockHandlers['h'] = function($scope, $key, $renderer, $contents) use (&$result) {
            $result = $contents;
        };
        $renderer->renderTree($tree);

        $this->assertSame($tree->c[0], $result);
    }

    /**
     * @depends testRenderBlockCapturesContents
     */
    function testRenderBlockSubrender()
    {
        $tpl = '{{#h}}foo {{v}} bar{{/h}}';
        $renderer = new Renderer;
        $renderer->valueHandlers['v'] = function($scope, $key) { return "hello"; };
        $renderer->blockHandlers['h'] = function($scope, $key, $renderer, $contents) {
            return $renderer->renderTree($contents);
        };
        $this->assertEquals("foo hello bar", $renderer->render($tpl));
    }

    /**
     * @depends testRenderBlockSubrender
     */
    function testRenderBlockNested()
    {
        $tpl = '{{#h}}foo {{#h}} bar {{v}} baz {{/h}} qux{{/h}}';
        $renderer = new Renderer;
        $renderer->valueHandlers['v'] = function($scope, $key) { return "hello"; };
        $renderer->blockHandlers['h'] = function($scope, $key, $renderer, $contents) {
            return "<".$renderer->renderTree($contents).">";
        };
        $this->assertEquals("<foo < bar hello baz > qux>", $renderer->render($tpl));
    }

    /**
     * @depends testRenderBlockSubrender
     */
    function testRenderBlockCanRenderTreeTwice()
    {
        $tpl = '{{#h}}1 {{#h}} 2 {{v}} 3 {{/h}} 4{{/h}}';
        $renderer = new Renderer;
        $renderer->valueHandlers['v'] = function($scope, $key) { return "9"; };
        $renderer->blockHandlers['h'] = function($scope, $key, $renderer, $contents) {
            return "<"
                .$renderer->renderTree($contents)
                ."|"
                .$renderer->renderTree($contents)
                .">"
            ;
        };
        $expected = "<1 < 2 9 3 | 2 9 3 > 4|1 < 2 9 3 | 2 9 3 > 4>";
        $this->assertEquals($expected, $renderer->render($tpl));
    }

    /**
     * @dataProvider dataRenderEscapedBraces
     */
    function testRenderEscapedBraces($tpl, $out)
    {
        $renderer = new Renderer;
        $renderer->valueHandlers['do'] = function($key) { return 'handled'; };
        $this->assertEquals($out, $renderer->render($tpl));
    }

    function dataRenderEscapedBraces()
    {
        return [
            ['{;{;do tag}}',     '{{do tag}}'],
            ['foo {;{; bar',     'foo {{ bar'],
            ['{;{;{{do tag}}}}', '{{handled}}'],
        ];
    }

    function testRenderEscapedBracesInsideTagInvalid()
    {
        $renderer = new Renderer;
        $this->setExpectedException("Tempe\ParseException");
        $renderer->render("{{do {{; }}");
    }

    function testRenderWithDodgyNode()
    {
        $renderer = new Renderer;
        $this->setExpectedException(
            'RuntimeException', 
            'Render failed: Unexpected node in parse tree: UNKNOWN(nope) on line 0'
        );
        $renderer->renderTree((object)['t'=>Renderer::P_ROOT, 'c'=>[(object)['t'=>'nope']]]);
    }

    function testRenderEmptyBlock()
    {
        $tpl = "{{#}}Hmm{{/}}";
        $renderer = new Renderer;
        $this->assertEmpty($renderer->render($tpl));
    }

    function testRenderWhitespaceBlock()
    {
        $tpl = "{{#\n\n\t\t  }}Hmm{{/\t\t\n\n  }}";
        $renderer = new Renderer;
        $this->assertEmpty($renderer->render($tpl));
    }

    function testRenderWhitespaceValue()
    {
        $tpl = "{{\n\n\t\t  }}";
        $renderer = new Renderer;
        $this->assertEmpty($renderer->render($tpl));
    }

    function testRenderEmptyValue()
    {
        $tpl = "{{}}";
        $renderer = new Renderer;
        $this->assertEmpty($renderer->render($tpl));
    }
}

class TestFilter
{
    function __call($name, $args)
    {
        return "$name(".implode(', ', $args).")";
    }
}
