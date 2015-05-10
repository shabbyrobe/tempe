<?php
namespace Tempe\Test;

use Tempe\Parser;
use Tempe\Renderer;
use Tempe\Lang\Basic as Lang;

class RendererTest extends \PHPUnit_Framework_TestCase
{
    function setUp()
    {
        $this->parser = new Parser();
    }

    function testRenderValueHandlerNoKeyNoFilter()
    {
        $tpl = '{=h}';
        $tree = $this->parser->parse($tpl);
        
        $lang = new Lang([
            'h'=>function() { return 'bingo'; }
        ]);
        $renderer = new Renderer($lang);
        $this->assertEquals('bingo', $renderer->render($tpl));
    }

    function testPipeline()
    {
        $tpl = '{=h | h | h}';
        $tree = $this->parser->parse($tpl);
        
        $lang = new Lang([
            'h'=>function($h, $in) { return $in.'a'; }
        ]);
        $renderer = new Renderer($lang);
        $this->assertEquals('aaa', $renderer->render($tpl));
    }

    /**
     * @dataProvider dataStringable
     */
    function testRenderValueHandlerStringableOutput($value, $expected)
    {
        $tpl = '{=h}';
        $tree = $this->parser->parse($tpl);
        
        $lang = new Lang([
            'h'=>function() use ($value) { return $value; }
        ]);
        $renderer = new Renderer($lang);
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
            [true, ''], // the renderer specifically prevents this from outputting '1'
        ];
    }

    function testRenderValueSingleHandlerWithKeys()
    {
        $tpl = '{=h key1 key2}';
        $tree = $this->parser->parse($tpl);
        
        $lang = new Lang([
            'h'=>function($h, $in, $ctx) { return 'bingo('.implode(", ", $h->args).')'; }
        ]);
        $renderer = new Renderer($lang);
        $this->assertEquals('bingo(key1, key2)', $renderer->render($tpl));
    }

    function testRenderValueMultipleHandlers()
    {
        $tpl = '{=h1 | h1 | h2}';
        $lang = new Lang([
            'h1'=>function($h, $in, $ctx) { return $in.'ICE '; },
            'h2'=>function($h, $in, $ctx) { return $in.'BABY '; },
        ]);
        $renderer = new Renderer($lang);
        $this->assertEquals('ICE ICE BABY ', $renderer->render($tpl));
    }

    function testRenderBlock()
    {
        $tpl = '{#h}{/}';
        $tree = $this->parser->parse($tpl);
        
        $lang = new Lang([
            'h'=>function() { return 'bingo'; }
        ]);
        $renderer = new Renderer($lang);
        $this->assertEquals('bingo', $renderer->render($tpl));
    }

    function testRenderBlockSingleHandler()
    {
        $tpl = '{#h key}{/}';
        $tree = $this->parser->parse($tpl);
        
        $lang = new Lang([
            'h' => function($h, $in, $ctx) { return 'bingo '.$h->args[0]; },
        ]);
        $renderer = new Renderer($lang);
        $this->assertEquals('bingo key', $renderer->render($tpl));
    }

    function testRenderBlockCapturesContents()
    {
        $tpl = '{#h}foo{/}';
        $tree = $this->parser->parse($tpl);
        $lang = new Lang([
            'h' => function($h, $in, $ctx) use (&$result) {
                $result = $ctx->node;
            },
        ]);
        $renderer = new Renderer($lang);

        $renderer->renderTree($tree);
        $this->assertSame($tree->nodes[0], $result);
    }

    /**
     * @depends testRenderBlockCapturesContents
     */
    function testRenderBlockSubrender()
    {
        $tpl = '{#h}foo {=v} bar{/}';
        $lang = new Lang([
            'v' => function($h, $in, $ctx) { return "hello"; },
            'h' => function($h, $in, $ctx) {
                return $ctx->renderer->renderTree($ctx->node);
            },
        ]);
        $renderer = new Renderer($lang);
        $this->assertEquals("foo hello bar", $renderer->render($tpl));
    }

    /**
     * @depends testRenderBlockSubrender
     */
    function testRenderBlockNested()
    {
        $tpl = '{#h}foo {#h} bar {=v} baz {/} qux{/}';
        $lang = new Lang([
            'v' => function($h, $in, $ctx) { return "hello"; },
            'h' => function($h, $in, $ctx) {
                return "<".$ctx->renderer->renderTree($ctx->node).">";
            },
        ]);
        $renderer = new Renderer($lang);
        $this->assertEquals("<foo < bar hello baz > qux>", $renderer->render($tpl));
    }

    /**
     * @depends testRenderBlockSubrender
     */
    function testRenderBlockCanRenderTreeTwice()
    {
        $tpl = '{#h}1 {#h} 2 {=v} 3 {/} 4{/}';
        $lang = new \Tempe\Lang\Basic([
            'v' => function($h, $in, $ctx) { return "9"; },
            'h' => function($h, $in, $ctx) {
                return "<"
                    .$ctx->renderer->renderTree($ctx->node)
                    ."|"
                    .$ctx->renderer->renderTree($ctx->node)
                    .">"
                ;
            },
        ]);
        $renderer = new Renderer($lang);
        $expected = "<1 < 2 9 3 | 2 9 3 > 4|1 < 2 9 3 | 2 9 3 > 4>";
        $this->assertEquals($expected, $renderer->render($tpl));
    }

    /**
     * @dataProvider dataRenderEscapedBraces
     */
    function testRenderEscapedBraces($tpl, $out)
    {
        $lang = new Lang([
            'do'=>function() { return 'handled'; }
        ]);
        $renderer = new Renderer($lang);
        $this->assertEquals($out, $renderer->render($tpl));
    }

    function dataRenderEscapedBraces()
    {
        return [
            ['{=;do tag}',     '{=do tag}'],
            ['foo {{=; bar',     'foo {{= bar'],
            ['foo {=;{=; bar',     'foo {={= bar'],
            ['{=;{=do tag}}', '{=handled}'],
        ];
    }

    function testRenderEscapedBracesInsideTagInvalid()
    {
        $renderer = new Renderer;
        $this->setExpectedException("Tempe\Exception\Parse");
        $renderer->render("{=do {=; }");
    }

    function testRenderWithDodgyNode()
    {
        $renderer = new Renderer;
        $this->setExpectedException(
            'RuntimeException', 
            'Render failed: Unexpected node in parse tree: UNKNOWN(nope) on line 0'
        );
        $renderer->renderTree((object)['type'=>Renderer::P_ROOT, 'nodes'=>[(object)['type'=>'nope']]]);
    }

    function testRenderEmptyBlock()
    {
        $tpl = "{#}Hmm{/}";
        $renderer = new Renderer;
        $this->assertEmpty($renderer->render($tpl));
    }

    function testRenderWhitespaceBlock()
    {
        $tpl = "{#\n\n\t\t  }Hmm{/\t\t\n\n  }";
        $renderer = new Renderer;
        $this->assertEmpty($renderer->render($tpl));
    }

    function testRenderWhitespaceValue()
    {
        $tpl = "{=\n\n\t\t  }";
        $renderer = new Renderer;
        $this->assertEmpty($renderer->render($tpl));
    }

    function testRenderEmptyValue()
    {
        $tpl = "{=}";
        $renderer = new Renderer;
        $this->assertEmpty($renderer->render($tpl));
    }

    function testRenderOldVersionFails()
    {
        $node = (object)['version'=>1, 'nodes'=>[]];
        $this->setExpectedException('InvalidArgumentException', 'Tree version 1 does not match expected version 2');
        (new Renderer)->renderTree($node);
    }

    function testRenderNewVersionFails()
    {
        $node = (object)['version'=>99999, 'nodes'=>[]];
        $this->setExpectedException('InvalidArgumentException', 'Tree version 99999 does not match expected version 2');
        (new Renderer)->renderTree($node);
    }

    function testRenderCheckPasses()
    {
        $tpl = "{= oi oi }";
        $lang = new \Tempe\Lang\Basic(
            ['oi'=>function() { return 'oi'; }],
            ['oi'=>['argc'=>1]]
        );
        $parser = new Parser();
        $tree = $parser->parse($tpl);
        $renderer = new Renderer($lang, null, !!'check');
        $this->assertEquals('oi', $renderer->renderTree($tree));
    }

    function testRenderCheck()
    {
        $tpl = "{= oi }";
        $lang = new \Tempe\Lang\Basic(
            ['oi'=>function() { return 'oi'; }],
            ['oi'=>['argc'=>1]]
        );
        $parser = new Parser();
        $tree = $parser->parse($tpl);
        $renderer = new Renderer($lang, null, !!'check');
        $this->setExpectedException('Tempe\Exception\Check');
        $renderer->renderTree($tree);
    }

    function testBreakResets()
    {
        $lang = new \Tempe\Lang\Basic([
            'break'=>function($h, $in, $ctx) { $ctx->break = true; },
            'nope' =>function($h, $in, $ctx) { throw new \Exception(1); },
            'check'=>function($h, $in, $ctx) { if ($ctx->break) $this->fail("Break did not reset"); },
        ]);
        $renderer = new Renderer($lang);
        $out = $renderer->render("{=break|nope}{=check}");
        $this->assertEmpty($out);
    }

    function testHandleEmptyValue()
    {
        $lang = new \Tempe\Lang\Basic([], [], function() {
            return 'EMPTY!';
        });
        $renderer = new Renderer($lang);
        $out = $renderer->render("{= }");
        $this->assertEquals('EMPTY!', $out);
    }

    function testHandleEmptyBlock()
    {
        $lang = new \Tempe\Lang\Basic([], [], function() {
            return 'EMPTY!';
        });
        $renderer = new Renderer($lang);
        $out = $renderer->render("{#}NOTHING{/}");
        $this->assertEquals('EMPTY!', $out);
    }

    function testNoNodes()
    {
        $renderer = new Renderer();
        $node = (object)['type'=>Renderer::P_VALUE];
        $this->assertEmpty($renderer->renderTree($node));
    }
}
