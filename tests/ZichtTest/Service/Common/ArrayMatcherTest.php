<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace SroTest\Service\Caching;

use \PHPUnit_Framework_TestCase;
use \Sro\Service\Request;
use \Sro\Service\Caching\ArrayMatcher;

/**
 * @covers Sro\Service\Caching\ArrayMatcher
 */
class ArrayMatcherTest extends PHPUnit_Framework_TestCase
{
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
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), null, array('unknown-attribute' => 'foo')))
        );
        $this->assertEquals(
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), null, array('five' => 'foo'))),
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), null, array('five' => 'foo', 'unknown-attribute' => 'foo')))
        );
    }

    function testKeyIsDifferentWhenAttributesAreAvailable()
    {
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'))),
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), null, array('five' => 'foo')))
        );
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), null, array('five' => 'foo'))),
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), null, array('five' => 'foo', 'three' => 'foo')))
        );
    }

    function testKeyIsDifferentWhenAttributeValuesAreDifferent()
    {
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), null, array('five' => 'foo'))),
            $this->matcher->getKey(new Request('methodA', array('a' => 'b'), null, array('five' => 'bar')))
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
        $this->assertEquals(5, $this->matcher->getTtl(new Request('methodA', array('a' => 'b'), null, array('five' => 'foo'))));
        // attribute five and three exists, three has the lowest ttl
        $this->assertEquals(3, $this->matcher->getTtl(new Request('methodA', array('a' => 'b'), null, array('five' => 'foo', 'three' => 'foo'))));
    }
}