<?php
namespace Tempe\Test\Filter;

use Tempe\Filter\WebEscaper;

class WebEscaperTest extends \PHPUnit_Framework_TestCase
{
    public function testAsExtension()
    {
        $ext = WebEscaper::asExtension();
        $this->assertInstanceOf('stdClass', $ext);
        $this->assertTrue(isset($ext->filters['as']));
        $this->assertInstanceOf('Tempe\Filter\WebEscaper', $ext->filters['as']);
    }

    /**
     * @depends testAsExtension
     */
    public function testAsExtensionWithFilterName()
    {
        $ext = WebEscaper::asExtension('quack');
        $this->assertInstanceOf('stdClass', $ext);
        $this->assertTrue(isset($ext->filters['quack']));
        $this->assertInstanceOf('Tempe\Filter\WebEscaper', $ext->filters['quack']);
    }
	
	public function testMultiWithArray()
	{
		$escaper = $this->getMockBuilder('Tempe\Filter\WebEscaper')
			->setMethods(['html', 'js'])
			->getMock()
		;
		$escaper->expects($this->once())->method('html');
		$escaper->expects($this->once())->method('js');
		$escaper->multi('value', ['html', 'js']);
	}
	
	public function testMultiWithArgs()
	{
		$escaper = $this->getMockBuilder('Tempe\Filter\WebEscaper')
			->setMethods(['html', 'js'])
			->getMock()
		;
		$escaper->expects($this->once())->method('html');
		$escaper->expects($this->once())->method('js');
		$escaper->multi('value', 'html', 'js');
	}
	
	public function testInvokeWithArray()
	{
		$escaper = $this->getMockBuilder('Tempe\Filter\WebEscaper')
			->setMethods(['html', 'js'])
			->getMock()
		;
		$escaper->expects($this->once())->method('html');
		$escaper->expects($this->once())->method('js');
		$escaper('value', ['html', 'js']);
	}
	
	public function testInvokeWithArgs()
	{
		$escaper = $this->getMockBuilder('Tempe\Filter\WebEscaper')
			->setMethods(['html', 'js'])
			->getMock()
		;
		$escaper->expects($this->once())->method('html');
		$escaper->expects($this->once())->method('js');
		$escaper('value', 'html', 'js');
	}

	/**
	 * @dataProvider dataForUnquotedHtmlAttrValid
	 */
	public function testUnquotedHtmlAttrValid($in, $test)
	{
		$this->assertEscapedHtmlAttrValid($in, $test, 'unquotedHtmlAttr');
	}
	
	public function dataForUnquotedHtmlAttrValid()
	{
		$tests = $this->getHtmlSpecialCharsCharacterTests();
		$chrs = [96, 64, 32, 33, 36, 37, 40, 41, 61, 43, 123, 124, 125, 91, 93];
		foreach ($chrs as $c)
			$tests[chr($c)] = "&#$c;";
		
		$tests["http://foo/bar?baz=qux&ding=dong"] = "http://foo/bar?baz&#61;qux&amp;ding&#61;dong";
		
		return $this->keyValueToTests($tests);
	}

	/**
	 * @dataProvider dataForHtmlAttrValid
	 */
	public function testHtmlAttrValid($in, $test)
	{
		$this->assertEscapedHtmlAttrValid($in, $test, 'htmlAttr');
	}
	
	public function dataForHtmlAttrValid()
	{
		$tests = $this->getHtmlSpecialCharsCharacterTests();
		$chrs = [96];
		foreach ($chrs as $c)
			$tests[chr($c)] = "&#$c;";
		
		$tests["http://foo/bar?baz=qux&ding=dong"] = "http://foo/bar?baz=qux&amp;ding=dong";
		
		return $this->keyValueToTests($tests);
	}

	/**
	 * @dataProvider dataForEmptyJs
	 */
	public function testJsReturnsEmptyString($value)
	{
		$result = (new WebEscaper)->js($value);
		$this->assertEquals('', $result);
	}

	/**
	 * @dataProvider dataForEmptyJs
	 */
	public function testQuotedJsReturnsEmptyQuotedString($value)
	{
		$result = (new WebEscaper)->quotedJs($value);
		$this->assertEquals('""', $result);
	}

	public function dataForEmptyJs()
	{
		return [
			[false], [null], [''],
		];
	}

	/**
	 * @dataProvider dataForQuotedJs
	 */
	public function testQuotedJs($value, $expected)
	{
		$result = (new WebEscaper)->quotedJs($value);
		$this->assertEquals($expected, $result);
	}

	public function dataForQuotedJs()
	{
		return [
			['foo', '"foo"'],
			["foo\nbar", '"foo\nbar"'],
			['foo "bar" baz', '"foo \"bar\" baz"'],
			["foo 'bar' baz", "\"foo 'bar' baz\""],
			[0, '"0"'],
			[1, '"1"'],
			[false, '""'],
			[null, '""'],
			[1.123, '"1.123"'],
		];
	}

	/**
	 * @dataProvider dataForJs
	 */
	public function testJs($value, $expected)
	{
		$result = (new WebEscaper)->js($value);
		$this->assertEquals($expected, $result);
	}

	public function dataForJs()
	{
		return [
			['foo', 'foo'],
			["foo\nbar", 'foo\nbar'],
			['foo "bar" baz', 'foo \"bar\" baz'],
			["foo 'bar' baz", "foo \'bar\' baz"],
			[0, '0'],
			[1, '1'],
			[false, ''],
			[null, ''],
			[1.123, '1.123'],
		];
	}
	
	private function assertEscapedHtmlAttrValid($in, $test, $func)
	{
		$e = new WebEscaper();
		$e->charset = 'UTF-8';
		$out = $e->$func($in);
		$this->assertEquals($test, $out);
		
		// this doesn't work if the input is just whitespace
		if ($test && preg_match("/[^\s]/", $in)) {
			$html = '<html><body><a href='.$out.' class=yep></a></body></html>';
			
			$dom = new \DOMDocument(null, 'UTF-8');
			$dom->loadHTML($html);
			$xp = new \DOMXPath($dom);
			$href = utf8_decode($xp->query("//html/body/a/@href")->item(0)->value);
			$this->assertEquals(html_entity_decode($test, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $href);
			
			$class = $xp->query("//html/body/a/@class")->item(0)->value;
			$this->assertEquals('yep', $class);
		}
	}
	
	private function keyValueToTests($in)
	{	
		$out = [];
		foreach ($in as $k=>$v)
			$out[] = [$k, $v];
		return $out;
	}
	
	private function getHtmlSpecialCharsCharacterTests()
	{
		$low = array_combine($chrs=array_map('chr', range(0, 127)), $chrs);
		
		// defaults - should always result in this.
		// null byte is not default, but it should be handled this way in all cases (for now)
		$low[chr(0)] = "";
		$low[chr(34)] = "&quot;";
		$low[chr(38)] = "&amp;";
		$low[chr(39)] = "&#039;";
		$low[chr(60)] = "&lt;";
		$low[chr(62)] = "&gt;";

		$high = array_combine(array_map('chr', range(128, 255)), array_fill(0, 128, json_decode('"\\uFFFD"')));
		return array_merge($low, $high);
	}

	/**
	 * @dataProvider dataForCssString
	 */
	public function testCssString($string, $expected)
	{
		$e = new WebEscaper();
		$e->charset = 'UTF-8';
		$result = $e->cssString($string);
		$this->assertEquals($expected, $result);
	}

	public function dataForCssString()
	{
		return [
			['foo "bar" baz', 'foo \"bar\" baz'],
			['foo \\"bar\\" baz', 'foo \\\\\"bar\\\\\" baz'],
			["foo 'bar' baz", "foo \'bar\' baz"],
			["foo\nbar", "foo\\0A bar"],
		];
	}

	/**
	 * @dataProvider dataForHtmlComment
	 */
	public function testHtmlComment($string)
	{
		$e = new WebEscaper();
		$e->charset = 'UTF-8';

		$pattern = '/(^>|^->|<!|--|-$)/';
		$result = $e->htmlComment($string);
		$this->assertNotRegexp($pattern, $result);

		// the spec is ambiguous about leading or trailing whitespace
		$result = trim($result);
		$this->assertNotRegexp($pattern, $result);
	}

	public function dataForHtmlComment()
	{
		return [
			['- foo -'],
			['-------'],
			[' ------- '],
			['foo bar -- foo bar'],
			['-> pants'],
			[' -> pants'],
			['> pants'],
			[' > pants > '],
			['<!-- nudda comment nest, eh'],
			['--> shouldnt close'],
		];
	}

    /**
     * @dataProvider dataForEscaperWithStringable
     */
    function testEscaperWithStringable($escaper, $val, $escaped)
    {
        $e = new WebEscaper();
        $result = $e->$escaper($val);
        $this->assertEquals($escaped, $result);
    }

    function dataForEscaperWithStringable()
    {
        return [
            ['quotedJs', new Stringable("foo"), '"foo"'],
            ['js', new Stringable("foo"), "foo"],
        ];
    }
}

class Stringable
{
    function __construct($string)
    {
        $this->string = $string;
    }

    function __toString()
    {
        return $this->string;
    }
}
