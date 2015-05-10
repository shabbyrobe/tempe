<?php
namespace Tempe\Lang\Core;

class EachTest extends \PHPUnit_Framework_TestCase
{
    function testEachUnsetNotAllowed()
    {
        $tpl = "{# each foo }yep{/}";
        $r = $this->getRenderer();
        $vars = [];
        $this->setExpectedException("Tempe\Exception\Render", "'each' could not find key 'foo' in scope at line 1");
        $r->render($tpl, $vars);
    }

    function testEachAssocArray()
    {
        $tpl = "{#each foo}{= get a1 } {=get a2}\n{/}";
        $expected = "foo bar\nbaz qux\n";
        $initialVars = $vars = [
            'foo'=>[
                ['a1'=>'foo', 'a2'=>'bar'],
                ['a1'=>'baz', 'a2'=>'qux'],
            ],
        ];
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));

        // make sure the scope changes don't propagate upwards
        $this->assertEquals($initialVars, $vars);
    }

    function testEachIterable()
    {
        $tpl = "{#each foo}{= get a1 } {=get a2}\n{/}";
        $expected = "foo bar\nbaz qux\n";
        $initialVars = $vars = [
            'foo'=>new \ArrayObject([
                ['a1'=>'foo', 'a2'=>'bar'],
                ['a1'=>'baz', 'a2'=>'qux'],
            ]),
        ];
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));

        // make sure the scope changes don't propagate upwards
        $this->assertEquals($initialVars, $vars);
    }

    function testEachNullInputNoKey()
    {
        $tpl = "{#get foo | each}a{/}";
        $vars = ['foo'=>null];
        $this->assertEquals("", $this->getRenderer()->render($tpl, $vars));
    }

    function testEachNullKey()
    {
        $tpl = "{#each foo}a{/}";
        $vars = ['foo'=>null];
        $this->assertEquals("", $this->getRenderer()->render($tpl, $vars));
    }

    function testEachInput()
    {
        $tpl = "{#get foo|each}{=get a1} {=get a2}\n{/}";
        $expected = "foo bar\nbaz qux\n";
        $initialVars = $vars = [
            'foo'=>[
                ['a1'=>'foo', 'a2'=>'bar'],
                ['a1'=>'baz', 'a2'=>'qux'],
            ],
        ];
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));

        // make sure the scope changes don't propagate upwards
        $this->assertEquals($initialVars, $vars);
    }

    function dataForEachNotIterable()
    {
        return [
            ['foo'],
            [1],
            [1.123],
            [(object)['foo'=>'bar']],
        ];
    }

    /**
     * @dataProvider dataForEachNotIterable
     */
    function testEachNotIterable($input)
    {
        $tpl = "{#each foo}{=get _value_} {/}";
        $vars = ['foo'=>$input];
        $r = $this->getRenderer();
        $this->setExpectedException("Tempe\Exception\Render", "'each' was not traversable at line 1");
        $r->render($tpl, $vars);
    }

    function testEachNumericArray()
    {
        $tpl = "{#each foo}{=get 0} {=get 1}\n{/}";
        $expected = "foo bar\nbaz qux\n";
        $initialVars = $vars = [
            'foo'=>[
                ['foo', 'bar'],
                ['baz', 'qux'],
            ],
        ];
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));

        // make sure the scope changes don't propagate upwards
        $this->assertEquals($initialVars, $vars);
    }

    function testEachMetaVars()
    {
        $tpl = "{#each foo}{=get _idx_}|{=get _num_ }) {=get _key_} => {=get _value_}\n{/}";
        $expected = "0|1) a => foo\n1|2) b => bar\n2|3) c => baz\n";
        $initialVars = $vars = [
            'foo'=>[
                'a'=>'foo',
                'b'=>'bar',
                'c'=>'baz',
            ],
        ];
        $r = $this->getRenderer();
        $this->assertEquals($expected, $r->render($tpl, $vars));

        // make sure the scope changes don't propagate upwards
        $this->assertEquals($initialVars, $vars);
    }

    private function getRenderer($ext=null)
    {
        return new \Tempe\Renderer(\Tempe\Lang\Factory::createBasic());
    }
}
