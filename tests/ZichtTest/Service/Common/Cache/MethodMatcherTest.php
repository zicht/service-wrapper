<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common\Caching;

use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\Cache\MethodMatcher;
use Zicht\Service\Common\Request;

class MethodMatcherTest extends TestCase
{
    /** @var MethodMatcher */
    protected $matcher;

    function setUp()
    {
        $this->matcher = new MethodMatcher(
            [
                'foo' => [
                    'default' => 123,
                    'attributes' => [],
                    'parameters' => [
                        [['bar'], 'baz'],
                    ],
                ],
            ]
        );
    }

    function testIsMatchMatchesExactRequest()
    {
        $this->assertTrue($this->matcher->isMatch(new Request('foo', ['bar' => 'baz'])));
    }

    function testIsMatchDoesNotConsiderUnspecifiedParameters()
    {
        $this->assertTrue($this->matcher->isMatch(new Request('foo', ['bar' => 'baz', 'foo' => 'bar'])));
    }

    function testIsMatchReturnsFalseIfParameterDoesNotMatch()
    {
        $this->assertFalse($this->matcher->isMatch(new Request('foo', [])));
    }

    function testIsMatchReturnsFalseIfMethodNameDoesNotMatch()
    {
        $this->assertFalse($this->matcher->isMatch(new Request('bar', [])));
    }

    function testIsExpungerIsFalse()
    {
        $this->assertFalse($this->matcher->isExpunger(new Request('bar', [])));
    }

    function testGetTtlReturnsConfiguredTtl()
    {
        $this->assertEquals(123, $this->matcher->getTtl(new Request('foo', ['bar' => 'baz'])));
    }

    function testGetKeyWillIncludeAllParameters()
    {
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('foo', [])),
            $this->matcher->getKey(new Request('foo', ['a' => 'b']))
        );
    }
}
