<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\Cache\ArrayMatcher;

class ArrayMatcherTest extends TestCase
{
    /** @var ArrayMatcher */
    public $matcher;

    function setUp()
    {
        $config = [
            'methodA' => [
                'fallback' => ['value' => 10, 'error' => 15, 'grace' => 30],
                'attributes' => [
                    'five' => ['value' => 5, 'error' => 5, 'grace' => 5],
                    'three' => ['value' => 3, 'error' => 3, 'grace' => 3],
                ],
            ],
            'methodB' => [
                'fallback' => ['value' => 15, 'error' => 20, 'grace' => 35],
                'attributes' => [
                    'five' => ['value' => 5, 'error' => 5, 'grace' => 5],
                    'three' => ['value' => 3, 'error' => 3, 'grace' => 3],
                ],
            ],
            'case' => [
                'fallback' => ['value' => 1, 'error' => 1, 'grace' => 1],
                'attributes' => [],
            ],
            'CASE' => [
                'fallback' => ['value' => 1, 'error' => 1, 'grace' => 1],
                'attributes' => [],
            ],
        ];
        $this->matcher = new ArrayMatcher($config);
    }

    function testIsMatchMatchesExactMethodName()
    {
        $this->assertTrue($this->matcher->isMatch(new Request('methodA')));
    }

    function testIsMatchCaseSensitive()
    {
        $this->assertFalse($this->matcher->isMatch(new Request('MethodA')));
        $this->assertFalse($this->matcher->isMatch(new Request('methoda')));
        $this->assertFalse($this->matcher->isMatch(new Request('MeThoDA')));
    }

    function testKeyIsDifferentIfParametersDiffer()
    {
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('methodA', ['a' => 'b'])),
            $this->matcher->getKey(new Request('methodA', ['a' => 'c']))
        );
    }

    function testKeyIsDifferentIfMethodNamesDiffer()
    {
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('methodA', ['a' => 'b'])),
            $this->matcher->getKey(new Request('methodB', ['a' => 'b']))
        );
    }

    function testKeyIsMatchingIsCaseSensitive()
    {
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('case', ['a' => 'b'])),
            $this->matcher->getKey(new Request('CASE', ['a' => 'b']))
        );
    }

    function testKeyIsNotInfluencedByUnconfiguredAttributes()
    {
        $this->assertEquals(
            $this->matcher->getKey(new Request('methodA', ['a' => 'b'])),
            $this->matcher->getKey(new Request('methodA', ['a' => 'b'], ['unknown-attribute' => 'foo']))
        );
        $this->assertEquals(
            $this->matcher->getKey(new Request('methodA', ['a' => 'b'], ['five' => 'foo'])),
            $this->matcher->getKey(new Request('methodA', ['a' => 'b'], ['five' => 'foo', 'unknown-attribute' => 'foo']))
        );
    }

    function testKeyIsDifferentWhenAttributesAreAvailable()
    {
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('methodA', ['a' => 'b'])),
            $this->matcher->getKey(new Request('methodA', ['a' => 'b'], ['five' => 'foo']))
        );
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('methodA', ['a' => 'b'], ['five' => 'foo'])),
            $this->matcher->getKey(new Request('methodA', ['a' => 'b'], ['five' => 'foo', 'three' => 'foo']))
        );
    }

    function testKeyIsDifferentWhenAttributeValuesAreDifferent()
    {
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('methodA', ['a' => 'b'], ['five' => 'foo'])),
            $this->matcher->getKey(new Request('methodA', ['a' => 'b'], ['five' => 'bar']))
        );
    }

    function testGetTtlReturnsSpecifiedFallbackTtlForMethodForAllParameters()
    {
        $this->assertEquals(['value' => 10, 'error' => 15, 'grace' => 30], $this->matcher->getTtlConfig(new Request('methodA', ['a' => 'b'])));
        $this->assertEquals(['value' => 10, 'error' => 15, 'grace' => 30], $this->matcher->getTtlConfig(new Request('methodA', ['a' => 'c'])));
        $this->assertEquals(['value' => 15, 'error' => 20, 'grace' => 35], $this->matcher->getTtlConfig(new Request('methodB', ['a' => 'b'])));
        $this->assertEquals(['value' => 15, 'error' => 20, 'grace' => 35], $this->matcher->getTtlConfig(new Request('methodB', ['a' => 'c'])));
    }

    function testGetTtlReturnsLowestAttribute()
    {
        // no attributes match, so using fallback
        $this->assertEquals(['value' => 10, 'error' => 15, 'grace' => 30], $this->matcher->getTtlConfig(new Request('methodA', ['a' => 'b'])));
        // attribute five matches and has the lowest ttl
        $this->assertEquals(['value' => 5, 'error' => 5, 'grace' => 5], $this->matcher->getTtlConfig(new Request('methodA', ['a' => 'b'], ['five' => 'foo'])));
        // attributes five and three match, three has the lowest ttl
        $this->assertEquals(['value' => 3, 'error' => 3, 'grace' => 3], $this->matcher->getTtlConfig(new Request('methodA', ['a' => 'b'], ['five' => 'foo', 'three' => 'foo'])));
    }

    public function testIsExpunger()
    {
        $this->assertFalse((new ArrayMatcher([]))->isExpunger($this->getMockBuilder(RequestInterface::class)->getMock()));
    }
}
