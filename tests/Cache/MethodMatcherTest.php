<?php declare(strict_types=1);
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

    public function setUp(): void
    {
        $config = [
            'foo' => [
                'fallback' => ['value' => 123, 'error' => 15, 'grace' => 30],
                'attributes' => [],
                'parameters' => [
                    [['bar'], 'baz'],
                ],
            ],
        ];
        $this->matcher = new MethodMatcher($config);
    }

    public function testIsMatchMatchesExactRequest()
    {
        $this->assertTrue($this->matcher->isMatch(new Request('foo', ['bar' => 'baz'])));
    }

    public function testIsMatchDoesNotConsiderUnspecifiedParameters()
    {
        $this->assertTrue($this->matcher->isMatch(new Request('foo', ['bar' => 'baz', 'foo' => 'bar'])));
    }

    public function testIsMatchReturnsFalseIfParameterDoesNotMatch()
    {
        $this->assertFalse($this->matcher->isMatch(new Request('foo', [])));
    }

    public function testIsMatchReturnsFalseIfMethodNameDoesNotMatch()
    {
        $this->assertFalse($this->matcher->isMatch(new Request('bar', [])));
    }

    public function testIsExpungerIsFalse()
    {
        $this->assertFalse($this->matcher->isExpunger(new Request('bar', [])));
    }

    public function testGetTtlReturnsConfiguredTtl()
    {
        $this->assertEquals(['value' => 123, 'error' => 15, 'grace' => 30], $this->matcher->getTtlConfig(new Request('foo', ['bar' => 'baz'])));
    }

    public function testGetKeyWillIncludeAllParameters()
    {
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('foo', [])),
            $this->matcher->getKey(new Request('foo', ['a' => 'b']))
        );
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('foo', [])),
            $this->matcher->getKey(new Request('foo', ['bar' => 'bar']))
        );
    }
}
