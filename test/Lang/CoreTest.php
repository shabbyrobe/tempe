<?php
namespace Tempe\Test\Lang;

use Tempe\Lang;
use Tempe\Renderer as R;
use Tempe\HandlerContext as Ctx;

class CoreTest extends \PHPUnit_Framework_TestCase
{
    function testConstructInvalidOptionsFails()
    {
        $this->setExpectedException('InvalidArgumentException');
        $c = new Lang\Part\Core(['helloHowAreYou'=>'yep', 'iAmWellThankYou'=>'indeedy']);
    }

    function testConstructEnablesAllBlocksByDefault()
    {
        $c = new Lang\Part\Core();
        $this->assertGreaterThan(0, $c->handlers);
    }

    function testBlacklist()
    {
        $c = new Lang\Part\Core(['blacklist'=>['each', 'show']]);

        $this->assertTrue(isset($c->handlers['var']));
        $this->assertTrue(isset($c->rules['var']));

        $this->assertFalse(isset($c->handlers['each']));
        $this->assertFalse(isset($c->rules['each']));
        $this->assertFalse(isset($c->handlers['show']));
        $this->assertFalse(isset($c->rules['show']));
    }

    function testWhitelist()
    {
        $c = new Lang\Part\Core(['whitelist'=>['each', 'show']]);

        $this->assertFalse(isset($c->handlers['var']));
        $this->assertFalse(isset($c->rules['var']));

        $this->assertTrue(isset($c->handlers['each']));
        $this->assertTrue(isset($c->rules['each']));
        $this->assertTrue(isset($c->handlers['show']));
        $this->assertTrue(isset($c->rules['show']));
    }
}
