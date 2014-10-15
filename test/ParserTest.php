<?php
namespace Tempe\Test;

use Tempe\Parser;
use Tempe\Renderer;
use Tempe\Helper;

class ParserTest extends \PHPUnit_Framework_TestCase
{    
    function setUp()
    {
        $this->parser = new Parser;
    }

    function testParseString()
    {
        $tpl = "foo bar baz";
        $tree = $this->parser->parse($tpl);
        $this->assertCount(1, $tree->c);
        $strNode = $tree->c[0];

        $this->assertEquals(Renderer::P_STRING, $strNode->t);
        $this->assertEquals("foo bar baz", $strNode->v);
        $this->assertEquals(1, $strNode->l);
    }

    function testParseStrings()
    {
        $tpl = "foo\nbar\n{{=}}\nbaz\n";
        $tree = $this->parser->parse($tpl);
        $this->assertCount(3, $tree->c);

        $node = $tree->c[0];
        $this->assertEquals(Renderer::P_STRING, $node->t);
        $this->assertEquals("foo\nbar\n", $node->v);
        $this->assertEquals(1, $node->l);

        $node = $tree->c[1];
        $this->assertEquals(Renderer::P_VALUE, $node->t);
        $this->assertEquals("{{=}}", $node->v);
        $this->assertEquals(3, $node->l);

        $node = $tree->c[2];
        $this->assertEquals(Renderer::P_STRING, $node->t);
        $this->assertEquals("\nbaz\n", $node->v);
        $this->assertEquals(3, $node->l);
    }

    function testWhitespaceValue()
    {
        $tpl = "{{  \t\t\n\n}}";
        $tree = $this->parser->parse($tpl);
        $expected = [
            ['t'=>Renderer::P_VALUE, 'l'=>1, 'v'=>$tpl, 'hc'=>null],
        ];
        $this->assertNodes($expected, $tree->c);
        $this->assertEquals($tpl, $this->parser->unparse($tree));
    }

    function testWhitespaceBlock()
    {
        $tpl = "{{#  \t\t\n\n}}{{/\n\n\t\t  }}";
        $tree = $this->parser->parse($tpl);
        $expected = [
            [
                'l'=>1,
                't'=>Renderer::P_BLOCK,
                'vo'=>"{{#  \t\t\n\n}}",
                "vc"=>"{{/\n\n\t\t  }}",
                'hc'=>null,
            ],
        ];
        $this->assertNodes($expected, $tree->c);
        $this->assertEquals($tpl, $this->parser->unparse($tree));
    }

    function testEmptyValue()
    {
        $tpl = "{{}}";
        $tree = $this->parser->parse($tpl);
        $expected = [
            ['t'=>Renderer::P_VALUE, 'l'=>1, 'v'=>$tpl, 'hc'=>null],
        ];
        $this->assertNodes($expected, $tree->c);
        $this->assertEquals($tpl, $this->parser->unparse($tree));
    }

    function testEmptyBlock()
    {
        $tpl = "{{#}}{{/}}";
        $tree = $this->parser->parse($tpl);
        $expected = [
            [
                't'=>Renderer::P_BLOCK, 'l'=>1, 'vo'=>'{{#}}', 'vc'=>'{{/}}', 'hc'=>null
            ],
        ];
        $this->assertNodes($expected, $tree->c);
        $this->assertEquals($tpl, $this->parser->unparse($tree));
    }

    function testParseUnclosedTagFails()
    {
        $this->setExpectedException("Tempe\ParseException", "Tag close mismatch, open was on line 1");
        $tree = $this->parser->parse('{{foo}}{{notclosed');
    }

    function testParseUnmatchedBlockFails()
    {
        $this->setExpectedException("Tempe\ParseException", "Unclosed block 'pants()' on line 1");
        $tree = $this->parser->parse('{{# pants}}');
    }

    function testParseUnmatchedBlockWithKeyFails()
    {
        $this->setExpectedException("Tempe\ParseException", "Unclosed block 'pants(key)' on line 1");
        $tree = $this->parser->parse('{{# pants key}}');
    }

    function testParseUnmatchedNestedBlockFails()
    {
        $this->setExpectedException("Tempe\ParseException", "Unclosed block 'trou()' on line 2");
        $tree = $this->parser->parse("{{# pants key}}\n{{# trou}}");
    }

    function testParseEscape()
    {
        $tree = $this->parser->parse('{;');
        $expected = [(object)['t'=>Renderer::P_ESC, 'v'=>'{;']];
        $this->assertNodes($expected, $tree->c);
    }

    function testParseEscapeAfterUnescapedBraceFails()
    {
        $this->setExpectedException("Tempe\ParseException", "Tag close mismatch, open was on line 1");
        $tree = $this->parser->parse('{{;');
    }

    function testParseMultipleEscape()
    {
        $tree = $this->parser->parse('{;{;');
        $expected = [
            ['t'=>Renderer::P_ESC, 'v'=>'{;'],
            ['t'=>Renderer::P_ESC, 'v'=>'{;'],
        ];
        $this->assertNodes($expected, $tree->c);
    }

    function testParseEscapeFollowedByTag()
    {
        $tree = $this->parser->parse('{;{{=}}');
        $expected = [
            ['t'=>Renderer::P_ESC, 'v'=>'{;'],
            ['t'=>Renderer::P_VALUE, 'v'=>'{{=}}'],
        ];
        $this->assertNodes($expected, $tree->c);
    }

    function assertNodes($expected, $nodes)
    {
        $valid = $this->compareNodes($expected, $nodes);
        $msg = '';
        if (!$valid) {
            ob_start();
            Helper::dumpTree((object)['t'=>Renderer::P_ROOT, 'c'=>$expected]);
            $e = ob_get_clean();

            ob_start();
            Helper::dumpTree((object)['t'=>Renderer::P_ROOT, 'c'=>$nodes]);
            $t = ob_get_clean();

            $d = new \SebastianBergmann\Diff\Differ;
            $msg = $d->diff($e, $t);
        }
        $this->assertTrue($valid, $msg);
    }

    function compareNodes($expected, $nodes)
    {
        if (count($expected) != count($nodes))
            goto oops;

        foreach ($expected as $idx=>$eNode) {
            $eNode = (object) $eNode;
            if (!isset($nodes[$idx]))
                throw new \UnexpectedValueException();
            $tNode = (object) $nodes[$idx];

            foreach (get_object_vars($eNode) as $k=>$v) {
                if ($k=='c')
                    continue;
                if ($v != $tNode->$k)
                    goto oops;
            }

            if (isset($eNode->c)) {
                if (!isset($tNode->c) || !$this->compareTree($eNode->c, $tNode->c))
                    goto oops;
                else
                    $this->compareNodes($eNode->c, $tNode->c);
            }
        }
        return true;
    oops:
        return false;
    }

    /**
     * @dataProvider dataValidValues
     */
    function testParseValidValue($tpl, $expected)
    {
        $tree = $this->parser->parse($tpl);
        $this->assertCount(1, $tree->c);
        $node = $tree->c[0];

        $this->assertEquals(Renderer::P_VALUE, $node->t);
        foreach ($expected as &$v)
            $v = (object)$v;
        $this->assertEquals($expected, $node->hc);
    }

    function dataValidValues()
    {
        return [
            ['{{h}}'                        , [['h'=>'h', 'k'=>null]]],
            ['{{handler}}'                  , [['h'=>'handler', 'k'=>null]]],
            ['{{ handler }}'                , [['h'=>'handler', 'k'=>null]]],

            ['{{h k}}'                      , [['h'=>'h', 'k'=>'k']]],
            ['{{handler key}}'              , [['h'=>'handler', 'k'=>'key']]],
            ['{{ handler key }}'            , [['h'=>'handler', 'k'=>'key']]],
            ['{{ handler  key }}'           , [['h'=>'handler', 'k'=>'key']]],

            ["{{handler|handler2}}"         , [['h'=>'handler', 'k'=>null], ['h'=>'handler2', 'k'=>null]]],
            ["{{handler|handler2}}"         , [['h'=>'handler', 'k'=>null], ['h'=>'handler2', 'k'=>null]]],
            ["{{handler|f}}"                , [['h'=>'handler', 'k'=>null], ['h'=>'f', 'k'=>null]]],
            ["{{handler|f1|f1}}"            , [['h'=>'handler', 'k'=>null], ['h'=>'f1', 'k'=>null], ['h'=>'f1', 'k'=>null]]],

            ["{{handler|f1|f2}}"            , [['h'=>'handler', 'k'=>null], ['h'=>'f1', 'k'=>null], ['h'=>'f2', 'k'=>null]]],
            ["{{handler|f1  |  f2}}"        , [['h'=>'handler', 'k'=>null], ['h'=>'f1', 'k'=>null], ['h'=>'f2', 'k'=>null]]],
            ["{{handler|f1 a1}}"            , [['h'=>'handler', 'k'=>null], ['h'=>'f1', 'k'=>'a1']]],
            ["{{handler|f1 a1|f2 a2}}"      , [['h'=>'handler', 'k'=>null], ['h'=>'f1', 'k'=>'a1'], ['h'=>'f2', 'k'=>'a2']]],

            // fluff that supports the Lang extension
            ['{{handler @key}}'             , [['h'=>'handler', 'k'=>'@key']]],
        ];
    }

    /**
     * @dataProvider dataValidBlocks
     */
    function testParseValidBlock($tpl, $expected)
    {
        $tree = $this->parser->parse($tpl);
        $this->assertCount(1, $tree->c);
        $node = $tree->c[0];

        $this->assertEquals(Renderer::P_BLOCK, $node->t);
        foreach ($expected as &$v) {
            $v = (object)$v;
            if (!isset($v->k)) $v->k = null;
        }

        $this->assertEquals($expected, $node->hc);

        $this->assertCount(1, $node->c);
        $strNode = $node->c[0];
        $this->assertEquals(Renderer::P_STRING, $strNode->t);
    }

    function dataValidBlocks()
    {
        return [
            ['{{#handler}} {{/handler}}'             , [['h'=>'handler']]],
            ['{{# handler }} {{/ handler }}'         , [['h'=>'handler']]],
            ['{{# handler key}} {{/ handler }}'      , [['h'=>'handler', 'k'=>'key']]],
            ['{{# handler key}} {{/ handler key }}'  , [['h'=>'handler', 'k'=>'key']]],
            ['{{# handler key }} {{/ handler key}}'  , [['h'=>'handler', 'k'=>'key']]],
            ['{{#handler|f1|f2}} {{/handler}}'       , [['h'=>'handler'], ['h'=>'f1'], ['h'=>'f2']]],
            ['{{#handler key|f1|f2}} {{/handler}}'   , [['h'=>'handler', 'k'=>'key'], ['h'=>'f1'], ['h'=>'f2']]],
            ['{{#h key|f1 a1|f2 a2}} {{/h}}'         , [['h'=>'h', 'k'=>'key'], ['h'=>'f1', 'k'=>'a1'], ['h'=>'f2', 'k'=>'a2']]],
            ['{{#h key  |f1  |  f2   a2  }} {{/h}}'  , [['h'=>'h', 'k'=>'key'], ['h'=>'f1'], ['h'=>'f2', 'k'=>'a2']]],
        ];
    }

    /**
     * @dataProvider dataUnparse
     */
    function testUnparse($tpl)
    {
        $tree = $this->parser->parse($tpl);
        $unparsed = $this->parser->unparse($tree);
        $this->assertEquals($tpl, $unparsed);
    }

    function dataUnparse()
    {
        $tests = [
            ["{;{;"],
            ["foo {;{; bar"],
            ["foo {;{{foo}} bar"],
        ];

        foreach ($this->dataValidValues() as $test)
            $tests[] = [$test[0]];

        foreach ($this->dataValidBlocks() as $test)
            $tests[] = [$test[0]];
        
        return $tests;
    }

    function testParseInvalidBlockHandlerCloseWithFilters()
    {
        $tpl = "{{# block }} {{/ block | doesnt | work}}";
        $this->setExpectedException(
            "Tempe\ParseException",
            "Only the first handler is valid in block close on line 1"
        );
        $tree = $this->parser->parse($tpl);
    }

    function testParseInvalidBlockHandlerCloseWithKeyAndFilters()
    {
        $tpl = "{{# block foo}} {{/ block foo | doesnt | work}}";
        $this->setExpectedException(
            "Tempe\ParseException",
            "Only the first handler is valid in block close on line 1"
        );
        $tree = $this->parser->parse($tpl);
    }

    function testParseInvalidBlockHandlerClose()
    {
        $tpl = "{{# block }} {{/ notblock }}";
        $this->setExpectedException(
            "Tempe\ParseException",
            "Handler close mismatch on line 1. Expected block, found notblock"
        );
        $tree = $this->parser->parse($tpl);
    }

    function testParseInvalidBlockHandlerKeyClose()
    {
        $tpl = "{{# block foo }} {{/ block bar }}";
        $this->setExpectedException(
            "Tempe\ParseException",
            "Handler key close mismatch on line 1. Expected foo, found bar"
        );
        $tree = $this->parser->parse($tpl);
    }

    /**
     * @dataProvider dataForNestedBlocks
     */
    function testNestedBlocks($tpl, $blocks, $str="test")
    {
        $tree = $this->parser->parse($tpl);
        $node = $tree;
        foreach ($blocks as $h) {
            $this->assertCount(1, $node->c);
            $this->assertEquals($h, $node->c[0]->hc[0]->h);
            $node = $node->c[0];
        }
        $this->assertEquals($str, $node->c[0]->v);
    }

    function dataForNestedBlocks()
    {
        $tests = [];

        for ($i=2; $i<=5; $i++) {
            $s = 'test';
            $b = [];
            for ($j=$i; $j>=1; $j--) {
                $b[] = "b{$j}";
                $s = "{{#b{$j}}}$s{{/b{$j}}}";
            }

            $tests[] = [$s, array_reverse($b)];
        }

        return $tests;
    }

    function testNewLinesUnix()
    {
        $tpl = "{{.}}\n\n{{.}}\n\n{{#.}}\n\n{{/.}}\n{{.}}\n";
    }
}
