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
        $this->assertCount(1, $tree->nodes);
        $strNode = $tree->nodes[0];

        $this->assertEquals(Renderer::P_STRING, $strNode->type);
        $this->assertEquals("foo bar baz", $strNode->v);
        $this->assertEquals(1, $strNode->line);
    }

    function testParseStrings()
    {
        $tpl = "foo\nbar\n{{}}\nbaz\n";
        $tree = $this->parser->parse($tpl);
        $this->assertCount(3, $tree->nodes);

        $node = $tree->nodes[0];
        $this->assertEquals(Renderer::P_STRING, $node->type);
        $this->assertEquals("foo\nbar\n", $node->v);
        $this->assertEquals(1, $node->line);

        $node = $tree->nodes[1];
        $this->assertEquals(Renderer::P_VALUE, $node->type);
        $this->assertEquals("{{}}", $node->v);
        $this->assertEquals(3, $node->line);

        $node = $tree->nodes[2];
        $this->assertEquals(Renderer::P_STRING, $node->type);
        $this->assertEquals("\nbaz\n", $node->v);
        $this->assertEquals(3, $node->line);
    }

    function testWhitespaceValue()
    {
        $tpl = "{{  \t\t\n\n}}";
        $tree = $this->parser->parse($tpl);
        $expected = [
            ['type'=>Renderer::P_VALUE, 'line'=>1, 'v'=>$tpl, 'chain'=>null],
        ];
        $this->assertNodes($expected, $tree->nodes);
        $this->assertEquals($tpl, $this->parser->unparse($tree));
    }

    function testWhitespaceBlock()
    {
        $tpl = "{{#  \t\t\n\n}}{{/\n\n\t\t  }}";
        $tree = $this->parser->parse($tpl);
        $expected = [
            [
                'line'=>1,
                'type'=>Renderer::P_BLOCK,
                'vo'=>"{{#  \t\t\n\n}}",
                "vc"=>"{{/\n\n\t\t  }}",
                'chain'=>null,
            ],
        ];
        $this->assertNodes($expected, $tree->nodes);
        $this->assertEquals($tpl, $this->parser->unparse($tree));
    }

    function testEmptyValue()
    {
        $tpl = "{{}}";
        $tree = $this->parser->parse($tpl);
        $expected = [
            ['type'=>Renderer::P_VALUE, 'line'=>1, 'v'=>$tpl, 'chain'=>null],
        ];
        $this->assertNodes($expected, $tree->nodes);
        $this->assertEquals($tpl, $this->parser->unparse($tree));
    }

    function testEmptyBlock()
    {
        $tpl = "{{#}}{{/}}";
        $tree = $this->parser->parse($tpl);
        $expected = [
            [
                'type'=>Renderer::P_BLOCK, 'line'=>1, 'vo'=>'{{#}}', 'vc'=>'{{/}}', 'chain'=>null
            ],
        ];
        $this->assertNodes($expected, $tree->nodes);
        $this->assertEquals($tpl, $this->parser->unparse($tree));
    }

    function testParseUnclosedTagFails()
    {
        $this->setExpectedException("Tempe\Exception\Parse", "Tag close mismatch (opened on line 1)");
        $tree = $this->parser->parse('{{foo}}{{notclosed');
    }

    function testParseUnmatchedBlockFails()
    {
        $this->setExpectedException("Tempe\Exception\Parse", "Unclosed block '(unnamed)' at line 1");
        $tree = $this->parser->parse('{{# pants}}');
    }

    function testParseUnclosedNamedBlockFails()
    {
        $this->setExpectedException("Tempe\Exception\Parse", "Unclosed block 'name' at line 1");
        $tree = $this->parser->parse('{{# name: pants}}');
    }

    function testParseUnmatchedNamedBlockFails()
    {
        $this->setExpectedException("Tempe\Exception\Parse", "Block close mismatch. Expected 'name', found 'nope' at line 1");
        $tree = $this->parser->parse('{{# name: pants}}{{/ nope }}');
    }

    function testParseUnclosedNestedBlockFails()
    {
        $this->setExpectedException("Tempe\Exception\Parse", "Unclosed block 'trou' at line 2");
        $tree = $this->parser->parse("{{# pants: test }}\n{{# trou: test }}");
    }

    function testParseEscape()
    {
        $tree = $this->parser->parse('{;');
        $expected = [(object)['type'=>Renderer::P_ESC, 'v'=>'{;']];
        $this->assertNodes($expected, $tree->nodes);
    }

    function testParseEscapeAfterUnescapedBraceFails()
    {
        $this->setExpectedException("Tempe\Exception\Parse", "Tag close mismatch (opened on line 1)");
        $tree = $this->parser->parse('{{;');
    }

    function testParseMultipleEscape()
    {
        $tree = $this->parser->parse('{;{;');
        $expected = [
            ['type'=>Renderer::P_ESC, 'v'=>'{;'],
            ['type'=>Renderer::P_ESC, 'v'=>'{;'],
        ];
        $this->assertNodes($expected, $tree->nodes);
    }

    function testParseEscapeFollowedByTag()
    {
        $tree = $this->parser->parse('{;{{}}');
        $expected = [
            ['type'=>Renderer::P_ESC, 'v'=>'{;'],
            ['type'=>Renderer::P_VALUE, 'v'=>'{{}}'],
        ];
        $this->assertNodes($expected, $tree->nodes);
    }

    function assertNodes($expected, $nodes)
    {
        $valid = $this->compareNodes($expected, $nodes);
        $msg = '';
        if (!$valid) {
            ob_start();
            Helper::dumpTree((object)['type'=>Renderer::P_ROOT, 'nodes'=>$expected]);
            $e = ob_get_clean();

            ob_start();
            Helper::dumpTree((object)['type'=>Renderer::P_ROOT, 'nodes'=>$nodes]);
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
                if ($k=='nodes')
                    continue;
                if ($v != $tNode->$k)
                    goto oops;
            }

            if (isset($eNode->nodes)) {
                if (!isset($tNode->nodes) || !$this->compareTree($eNode->nodes, $tNode->nodes))
                    goto oops;
                else
                    $this->compareNodes($eNode->nodes, $tNode->nodes);
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
        $this->assertCount(1, $tree->nodes);
        $node = $tree->nodes[0];

        $this->assertEquals(Renderer::P_VALUE, $node->type);
        $expected = $this->createExpectedHandlers($expected);
        $this->assertEquals($expected, $node->chain);
    }

    function dataValidValues()
    {
        return [
            ['{{h}}'                            , ['h'] ],
            ['{{handler}}'                      , ['handler'] ],
            ['{{ handler }}'                    , ['handler'] ],

            ['{{h k}}'                          , [['h', ['k']]] ],
            ['{{handler key}}'                  , [['handler', ['key']]] ],
            ['{{ handler key }}'                , [['handler', ['key']]] ],
            ['{{ handler  key }}'               , [['handler', ['key']]] ],

            ["{{handler|handler}}"              , ['handler', 'handler'] ],
            ["{{handler|handler2}}"             , ['handler', 'handler2'] ],
            ["{{handler | handler}}"            , ['handler', 'handler'] ],
            ["{{h1|h2|h3}}"                     , ['h1', 'h2', 'h3'] ],
            ["{{h1 |  h2    |     h3}}"         , ['h1', 'h2', 'h3'] ],
            ["{{h1\t|\t \th2\t\n |\t\n \n h3}}" , ['h1', 'h2', 'h3'] ],

            ["{{handler|f1 a1}}"                , ['handler', ['f1', ['a1']]] ],
            ["{{handler|f1 a1|f2 a2}}"          , ['handler', ['f1', ['a1']], ['f2', ['a2']]] ],
            ["{{handler|f1  a1  b1   c1}}"      , ['handler', ['f1', ['a1', 'b1', 'c1']]] ],
            ["{{handler|f1 a1 b1|f2 a2 b2}}"    , ['handler', ['f1', ['a1', 'b1']], ['f2', ['a2', 'b2']]] ],
        ];
    }

    private function createExpectedHandlers($handlerShorthand)
    {
        $expected = [];
        foreach ($handlerShorthand as $v) {
            $v = (array)$v;
            $h = ['handler'=>$v[0], 'args'=>[], 'argc'=>0];
            if (isset($v[1])) {
                $h['args'] = $v[1];
                $h['argc'] = count($v[1]);
            }
            $expected[] = $h;
        }
        return $expected;
    }

    /**
     * @dataProvider dataValidBlocks
     */
    function testParseValidBlock($tpl, $id, $handlers)
    {
        $tree = $this->parser->parse($tpl);
        $this->assertCount(1, $tree->nodes);
        $node = $tree->nodes[0];

        $this->assertEquals(Renderer::P_BLOCK, $node->type);
        $expected = $this->createExpectedHandlers($handlers);

        $this->assertEquals($id, $node->id);
        $this->assertEquals($expected, $node->chain);

        $this->assertCount(1, $node->nodes);
        $strNode = $node->nodes[0];
        $this->assertEquals(Renderer::P_STRING, $strNode->type);
    }

    function dataValidBlocks()
    {
        return [
            ['{{#handler}} {{/}}'                    , null  , ['handler'] ],
            ['{{# handler }} {{/ }}'                 , null  , ['handler'] ],
            ['{{# handler key}} {{/ }}'              , null  , [['handler', ['key']]] ],
            ['{{# handler key1 key2}} {{/ }}'        , null  , [['handler', ['key1', 'key2']]] ],
            ['{{#name:handler }} {{/name}}'           , 'name', ['handler'] ],
            ['{{# name: handler }} {{/ name }}'       , 'name', ['handler'] ],
            ['{{#}} {{/}}'                            , null  , [] ],
            ['{{# }} {{/}}'                           , null  , [] ],
            ['{{#name: }} {{/name}}'                  , 'name', [] ],
            ['{{#handler|f1|f2}} {{/}}'              , null  , ['handler', 'f1', 'f2'] ],
            ['{{#handler key|f1|f2}} {{/}}'          , null  , [['handler', ['key']], 'f1', 'f2'] ],
            ['{{#h key|f1 a1|f2 a2}} {{/}}'          , null  , [['h', ['key']], ['f1', ['a1']], ['f2', ['a2']]] ],
            ['{{#h key  |f1  |  f2   a2  }} {{/}}'   , null  , [['h', ['key']], 'f1', ['f2', ['a2']]] ],
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

    function testParseUnnamedBlockWithNamedCloseFails()
    {
        $tpl = "{{# block }} {{/ block }}";
        $this->setExpectedException(
            "Tempe\Exception\Parse",
            "Block close mismatch. Expected '', found 'block' at line 1"
        );
        $tree = $this->parser->parse($tpl);
    }

    /**
     * @dataProvider dataForNestedNamedBlocks
     */
    function testNestedNamed($tpl, $blocks, $str="test")
    {
        $tree = $this->parser->parse($tpl);
        $node = $tree;
        foreach ($blocks as $h) {
            $this->assertCount(1, $node->nodes);
            $this->assertEquals('b'.$h, $node->nodes[0]->id);
            $this->assertEquals('h'.$h, $node->nodes[0]->chain[0]['handler']);
            $node = $node->nodes[0];
        }
        $this->assertEquals($str, $node->nodes[0]->v);
    }

    function dataForNestedNamedBlocks()
    {
        $tests = [];

        for ($i=2; $i<=5; $i++) {
            $s = 'test';
            $b = [];
            for ($j=$i; $j>=1; $j--) {
                $b[] = $j;
                $s = "{{# b{$j}: h{$j}}}$s{{/b{$j}}}";
            }

            $tests[] = [$s, array_reverse($b)];
        }

        return $tests;
    }

    /**
     * @dataProvider dataForNestedBlocks
     */
    function testNestedBlocks($tpl, $blocks, $str="test")
    {
        $tree = $this->parser->parse($tpl);
        $node = $tree;
        foreach ($blocks as $h) {
            $this->assertCount(1, $node->nodes);
            $this->assertEquals($h, $node->nodes[0]->chain[0]['handler']);
            $node = $node->nodes[0];
        }
        $this->assertEquals($str, $node->nodes[0]->v);
    }

    function dataForNestedBlocks()
    {
        $tests = [];

        for ($i=2; $i<=5; $i++) {
            $s = 'test';
            $b = [];
            for ($j=$i; $j>=1; $j--) {
                $b[] = "b{$j}";
                $s = "{{#b{$j}}}$s{{/}}";
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
