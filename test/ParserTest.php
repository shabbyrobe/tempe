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
        $this->assertEquals(Renderer::P_VAR, $node->t);
        $this->assertEquals("{{=}}", $node->v);
        $this->assertEquals(3, $node->l);

        $node = $tree->c[2];
        $this->assertEquals(Renderer::P_STRING, $node->t);
        $this->assertEquals("\nbaz\n", $node->v);
        $this->assertEquals(3, $node->l);
    }

    function testWhitespaceTag()
    {
        $tpl = "{{  \t\t\n\n}}";
        $tree = $this->parser->parse($tpl);
        $expected = [
            ['t'=>Renderer::P_VAR, 'v'=>$tpl, 'h'=>null, 'k'=>null, 'f'=>[]],
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
        $this->setExpectedException("Tempe\ParseException", "Unclosed block pants() on line 1");
        $tree = $this->parser->parse('{{# pants}}');
    }

    function testParseUnmatchedBlockWithKeyFails()
    {
        $this->setExpectedException("Tempe\ParseException", "Unclosed block pants(key) on line 1");
        $tree = $this->parser->parse('{{# pants key}}');
    }

    function testParseUnmatchedNestedBlockFails()
    {
        $this->setExpectedException("Tempe\ParseException", "Unclosed block trou() on line 2");
        $tree = $this->parser->parse("{{# pants key}}\n{{# trou}}");
    }

    function testParseEscape()
    {
        $tree = $this->parser->parse('{!');
        $expected = [(object)['t'=>Renderer::P_ESC, 'v'=>'{!']];
        $this->assertNodes($expected, $tree->c);
    }

    function testParseEscapeAfterUnescapedBraceFails()
    {
        $this->setExpectedException("Tempe\ParseException", "Tag close mismatch, open was on line 1");
        $tree = $this->parser->parse('{{!');
    }

    function testParseMultipleEscape()
    {
        $tree = $this->parser->parse('{!{!');
        $expected = [
            ['t'=>Renderer::P_ESC, 'v'=>'{!'],
            ['t'=>Renderer::P_ESC, 'v'=>'{!'],
        ];
        $this->assertNodes($expected, $tree->c);
    }

    function testParseEscapeFollowedByTag()
    {
        $tree = $this->parser->parse('{!{{=}}');
        $expected = [
            ['t'=>Renderer::P_ESC, 'v'=>'{!'],
            ['t'=>Renderer::P_VAR, 'v'=>'{{=}}'],
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
     * @dataProvider dataValidVars
     */
    function testParseValidVar($tpl, $expected)
    {
        $tree = $this->parser->parse($tpl);
        $this->assertCount(1, $tree->c);
        $node = $tree->c[0];

        $this->assertEquals(Renderer::P_VAR, $node->t);
        foreach ($expected as $k=>$v)
            $this->assertEquals($v, $node->$k);
    }

    function dataValidVars()
    {
        return [
            ['{{h}}'                        , ['h'=>'h', 'k'=>null]],
            ['{{handler}}'                  , ['h'=>'handler', 'k'=>null]],
            ['{{ handler }}'                , ['h'=>'handler', 'k'=>null]],

            ['{{h k}}'                      , ['h'=>'h', 'k'=>'k']],
            ['{{handler key}}'              , ['h'=>'handler', 'k'=>'key']],
            ['{{ handler key }}'            , ['h'=>'handler', 'k'=>'key']],
            ['{{ handler  key }}'           , ['h'=>'handler', 'k'=>'key']],

            ["{{handler|filter1}}"          , ['h'=>'handler', 'k'=>null, 'f'=>[['filter1']]]],
            ["{{handler|f}}"                , ['h'=>'handler', 'k'=>null, 'f'=>[['f']]]],
            ["{{handler|f1|f1}}"            , ['h'=>'handler', 'k'=>null, 'f'=>[['f1'], ['f1']]]],
            ["{{ handler | filter1 }}"      , ['h'=>'handler', 'k'=>null, 'f'=>[['filter1']]]],

            ["{{handler|f1|f2}}"            , ['h'=>'handler', 'k'=>null, 'f'=>[['f1'], ['f2']]]],
            ["{{handler|f1  |  f2}}"        , ['h'=>'handler', 'k'=>null, 'f'=>[['f1'], ['f2']]]],
            ["{{handler|f1.a1}}"            , ['h'=>'handler', 'k'=>null, 'f'=>[['f1', 'a1']]]],
            ["{{handler|f1.a1|f2.a2}}"      , ['h'=>'handler', 'k'=>null, 'f'=>[['f1', 'a1'], ['f2', 'a2']]]],

            // fluff that supports the Lang extension
            ['{{=}}'                        , ['h'=>'=']],
            ['{{handler @key}}'             , ['h'=>'handler', 'k'=>'@key']],
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
        foreach ($expected as $k=>$v)
            $this->assertEquals($v, $node->$k);

        $this->assertCount(1, $node->c);
        $strNode = $node->c[0];
        $this->assertEquals(Renderer::P_STRING, $strNode->t);
    }

    function dataValidBlocks()
    {
        return [
            ['{{#handler}} {{/handler}}'             , ['h'=>'handler', 'k'=>null]],
            ['{{# handler }} {{/ handler }}'         , ['h'=>'handler', 'k'=>null]],
            ['{{# handler key}} {{/ handler }}'      , ['h'=>'handler', 'k'=>'key']],
            ['{{# handler key}} {{/ handler key }}'  , ['h'=>'handler', 'k'=>'key']],
            ['{{# handler key }} {{/ handler key}}'  , ['h'=>'handler', 'k'=>'key']],
            ['{{#handler|f1|f2}} {{/handler}}'       , ['h'=>'handler', 'k'=>null,  'f'=>[['f1'], ['f2']]]],
            ['{{#handler key|f1|f2}} {{/handler}}'   , ['h'=>'handler', 'k'=>'key', 'f'=>[['f1'], ['f2']]]],
            ['{{#h k|f1.a1|f2.a2}} {{/h}}'           , ['h'=>'h', 'k'=>'k', 'f'=>[['f1', 'a1'], ['f2', 'a2']]]],
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
            ["{!{!"],
            ["foo {!{! bar"],
            ["foo {!{{foo}} bar"],
        ];

        foreach ($this->dataValidVars() as $test)
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
            "Handler close on line 1 contained filters"
        );
        $tree = $this->parser->parse($tpl);
    }

    function testParseInvalidBlockHandlerCloseWithKeyAndFilters()
    {
        $tpl = "{{# block foo}} {{/ block foo | doesnt | work}}";
        $this->setExpectedException(
            "Tempe\ParseException",
            "Handler close on line 1 contained filters"
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
            $this->assertEquals($h, $node->c[0]->h);
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
