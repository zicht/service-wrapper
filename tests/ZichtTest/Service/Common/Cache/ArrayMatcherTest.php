<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\Cache\ArrayMatcher;

class ArrayMatcherTest extends TestCase
{
    /**
     * @var ArrayMatcher
     */
    public $matcher;

    function setUp()
    {
        $config = [
            'methodA' => ['default' => 10, 'attributes' => ['five' => 5, 'three' => 3]],
            'methodB' => ['default' => 15, 'attributes' => ['five' => 5, 'three' => 3]],
        ];
        $this->matcher = new ArrayMatcher($config);
    }

    function testIsMatchMatchesExactMethodName()
    {
        $this->assertTrue($this->matcher->isMatch(new Request('methodA')));
    }

    function testIsMatchIgnoresCase()
    {
        $this->assertTrue($this->matcher->isMatch(new Request('MeThoDA')));
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

    function testKeyIsMatchingIsCaseInsensitive()
    {
        $this->assertEquals(
            $this->matcher->getKey(new Request('MeThOdA', ['a' => 'b'])),
            $this->matcher->getKey(new Request('methodA', ['a' => 'b']))
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

    function testGetTtlReturnsSpecifiedTtlForMethodForAllParameters()
    {
        $this->assertEquals(10, $this->matcher->getTtl(new Request('methodA', ['a' => 'b'])));
        $this->assertEquals(10, $this->matcher->getTtl(new Request('methodA', ['a' => 'c'])));
        $this->assertEquals(15, $this->matcher->getTtl(new Request('methodB', ['a' => 'b'])));
        $this->assertEquals(15, $this->matcher->getTtl(new Request('methodB', ['a' => 'c'])));
    }

    function testGetTtlReturnsSpecifiedTtlAndIgnoresCase()
    {
        $this->assertEquals(10, $this->matcher->getTtl(new Request('MeThOdA', ['a' => 'b'])));
        $this->assertEquals(15, $this->matcher->getTtl(new Request('MeThOdB', ['a' => 'b'])));
    }

    function testGetTtlReturnsLowestAttribute()
    {
        // default 10 is the lowest
        $this->assertEquals(10, $this->matcher->getTtl(new Request('methodA', ['a' => 'b'])));
        // attribute five exists and has the lowest ttl
        $this->assertEquals(5, $this->matcher->getTtl(new Request('methodA', ['a' => 'b'], ['five' => 'foo'])));
        // attribute five and three exists, three has the lowest ttl
        $this->assertEquals(3, $this->matcher->getTtl(new Request('methodA', ['a' => 'b'], ['five' => 'foo', 'three' => 'foo'])));
    }


    public function testIsExpunger()
    {
        $this->assertFalse((new ArrayMatcher([]))->isExpunger($this->getMockBuilder(RequestInterface::class)->getMock()));
    }
}
