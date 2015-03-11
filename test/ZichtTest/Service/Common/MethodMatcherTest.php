<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace SroTest\Service\Caching;

use \PHPUnit_Framework_TestCase;
use \Sro\Service\Caching\MethodMatcher;
use \Sro\Service\Request;
use \Sro\Service\Caching\EntityMatcher;

/**
 * @covers Sro\Service\Caching\MethodMatcher
 */
class MethodMatcherTest extends PHPUnit_Framework_TestCase {
    /** @var EntityMatcher */
    protected $matcher;

    function setUp() {
        $this->matcher = new MethodMatcher(
            array(
                'foo' => array(
                    'default' => 123,
                    'attributes' => array(),
                    'parameters' => array(
                        array(array('bar'), 'baz')
                    )
                )
            )
        );
    }

    function testIsMatchMatchesExactRequest() {
        $this->assertTrue($this->matcher->isMatch(new Request('foo', array('bar' => 'baz'))));
    }

    function testIsMatchDoesNotConsiderUnspecifiedParameters() {
        $this->assertTrue($this->matcher->isMatch(new Request('foo', array('bar' => 'baz', 'foo' => 'bar'))));
    }

    function testIsMatchReturnsFalseIfParameterDoesNotMatch() {
        $this->assertFalse($this->matcher->isMatch(new Request('foo', array())));
    }

    function testIsMatchReturnsFalseIfMethodNameDoesNotMatch() {
        $this->assertFalse($this->matcher->isMatch(new Request('bar', array())));
    }

    function testIsExpungerIsFalse() {
        $this->assertFalse($this->matcher->isExpunger(new Request('bar', array())));
    }

    function testGetTtlReturnsConfiguredTtl() {
        $this->assertEquals(123, $this->matcher->getTtl(new Request('foo', array('bar' => 'baz'))));
    }

    function testGetKeyWillIncludeAllParameters() {
        $this->assertNotEquals(
            $this->matcher->getKey(new Request('foo', array())),
            $this->matcher->getKey(new Request('foo', array('a' => 'b')))
        );
    }
}