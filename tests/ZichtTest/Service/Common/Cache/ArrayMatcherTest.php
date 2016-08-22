<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

use \PHPUnit_Framework_TestCase;

use \Zicht\Service\Common\Response;
use \Zicht\Service\Common\Cache\ArrayMatcher;

/**
 * @covers Zicht\Service\Common\Cache\ArrayMatcher
 */
class ArrayMatcherTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var ArrayMatcher
     */
    public $matcher;

    function setUp()
    {
        $config = array(
            'methodA' => array('default' => 10, 'attributes' => array('five' => 5, 'three' => 3)),
            'methodB' => array('default' => 15, 'attributes' => array('five' => 5, 'three' => 3)),
        );
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
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'))),
            $this->matcher->getKey(new Request('methodA', array('a' => 'c')))
        );
    }

    function testKeyIsDifferentIfMethodNamesDiffer()
    {
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'))),
            $this->matcher->getKey(new Request('methodB', array('a' => 'b')))
        );
    }

    function testKeyIsMatchingIsCaseInsensitive()
    {
        $this->assertEquals(
            $this->matcher->getKey(new Request('MeThOdA', array('a' => 'b'))),
            $this->matcher->getKey(new Request('methodA', array('a' => 'b')))
        );
    }

    function testKeyIsNotInfluencedByUnconfiguredAttributes()
    {
        $this->assertEquals(
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'))),
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), array('unknown-attribute' => 'foo')))
        );
        $this->assertEquals(
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), array('five' => 'foo'))),
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), array('five' => 'foo', 'unknown-attribute' => 'foo')))
        );
    }

    function testKeyIsDifferentWhenAttributesAreAvailable()
    {
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'))),
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), array('five' => 'foo')))
        );
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), array('five' => 'foo'))),
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), array('five' => 'foo', 'three' => 'foo')))
        );
    }

    function testKeyIsDifferentWhenAttributeValuesAreDifferent()
    {
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), array('five' => 'foo'))),
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), array('five' => 'bar')))
        );
    }

    function testGetTtlReturnsSpecifiedTtlForMethodForAllParameters()
    {
        $this->assertEquals(10, $this->matcher->getTtl(new Request('methodA', array('a' => 'b'))));
        $this->assertEquals(10, $this->matcher->getTtl(new Request('methodA', array('a' => 'c'))));
        $this->assertEquals(15, $this->matcher->getTtl(new Request('methodB', array('a' => 'b'))));
        $this->assertEquals(15, $this->matcher->getTtl(new Request('methodB', array('a' => 'c'))));
    }

    function testGetTtlReturnsSpecifiedTtlAndIgnoresCase()
    {
        $this->assertEquals(10, $this->matcher->getTtl(new Request('MeThOdA', array('a' => 'b'))));
        $this->assertEquals(15, $this->matcher->getTtl(new Request('MeThOdB', array('a' => 'b'))));
    }

    function testGetTtlReturnsLowestAttribute()
    {
        // default 10 is the lowest
        $this->assertEquals(10, $this->matcher->getTtl(new Request('methodA', array('a' => 'b'))));
        // attribute five exists and has the lowest ttl
        $this->assertEquals(5, $this->matcher->getTtl(new Request('methodA', array('a' => 'b'), array('five' => 'foo'))));
        // attribute five and three exists, three has the lowest ttl
        $this->assertEquals(3, $this->matcher->getTtl(new Request('methodA', array('a' => 'b'), array('five' => 'foo', 'three' => 'foo'))));
    }


    public function testIsExpunger()
    {
        $this->assertFalse((new ArrayMatcher([]))->isExpunger($this->getMock(RequestInterface::class)));
    }
}