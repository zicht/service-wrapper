<?php

namespace ZichtTest\Service\Common;

use PHPUnit_Framework_TestCase;
use Zicht\Service\Common\Request;

/**
 * @covers Zicht\Service\Common\Request
 */
class RequestTest extends PHPUnit_Framework_TestCase
{
    function testGetParameterDeep()
    {
        $value = rand(1, 9999);
        $request = new Request('', array(array('a' => array('b' => array('c' => $value)))));
        $this->assertEquals($value, $request->getParameterDeep(array(0, 'a', 'b', 'c')));
    }


    function testGetParameterDeepReturnsNullOnInvalidPath()
    {
        $value = rand(1, 9999);
        $request = new Request('', array(array('a' => array('b' => array('c' => $value)))));
        $this->assertEquals(null, $request->getParameterDeep(array(0, 'a', 'b', 'qux')));
    }

    function testGetParameterDeepObjects()
    {
        $value = rand(1, 9999);
        $request = new Request('', array((object)array('a' => (object)array('b' => (object)array('c' => $value)))));
        $this->assertEquals($value, $request->getParameterDeep(array(0, 'a', 'b', 'c')));
    }

    function testSetParameterDeep()
    {
        $value = rand(1, 9999);
        $value2 = rand(10000, 19999);
        $request = new Request('', array(array('a' => array('b' => array('c' => $value)))));
        $request->setParameterDeep(array(0, 'a', 'b', 'c'), $value2);
        $this->assertEquals($value2, $request->getParameterDeep(array(0, 'a', 'b', 'c')));
    }

    function testSetParameterDeepIfValueNotPreviouslySet()
    {
        $value2 = rand(10000, 19999);
        $request = new Request('', array());
        $request->setParameterDeep(array(0, 'a', 'b', 'c'), $value2);
        $this->assertEquals($value2, $request->getParameterDeep(array(0, 'a', 'b', 'c')));
    }


    function testSetParameterDeepIfDeepValueNotPreviouslySet()
    {
        $value2 = rand(10000, 19999);
        $request = new Request('', array(['a' => (object)['b' => null]]));
        $request->setParameterDeep(array(0, 'a', 'b', 'c', 'd'), $value2);
        $this->assertEquals($value2, $request->getParameterDeep(array(0, 'a', 'b', 'c', 'd')));
    }


    public function testMethodEquality()
    {
        $request = new Request('FOO');
        $this->assertTrue($request->isMethod('FOO'));
        $this->assertTrue($request->isMethod('foo'));
        $this->assertTrue($request->isAnyMethod(['foo']));
        $this->assertTrue($request->isAnyMethod(['bar', 'foo']));
        $this->assertFalse($request->isAnyMethod(['bar']));
        $this->assertFalse($request->isMethod('bar'));
    }


    public function testStringRepresentation()
    {
        $request = new Request('FOO', ['prop' => 'value']);
        $s = (string)$request;

        $this->assertRegExp('/FOO/', $s);
        $this->assertRegExp('/prop/', $s);
        $this->assertRegExp('/value/', $s);
    }


    public function testAttributes()
    {
        $request = new Request('');

        $this->assertEquals(false, $request->hasAttribute('a'));
        $this->assertEquals(null, $request->getAttribute('a'));
        $this->assertEquals('default', $request->getAttribute('a', 'default'));
        $request->setAttribute('a', 'b');
        $this->assertEquals('b', $request->getAttribute('a'));

        $request->setAttributes(['q' => 'x']);
        $this->assertEquals(true, $request->hasAttribute('q'));

        // previously set attributes are overwritten.
        $this->assertEquals(false, $request->hasAttribute('a'));
        $this->assertEquals('x', $request->getAttribute('q'));
        $this->assertEquals(['q' => 'x'], $request->getAttributes());

        $request->setAttributes([]);
        $this->assertEquals(false, $request->hasAttribute('q'));
        $this->assertEquals(false, $request->hasAttribute('a'));
    }

    public function testGetAttributesDeep()
    {
        $nestedAttributes1 = ['foo' => ['bar' => 'yo']];
        $request = new Request('');
        $request->setAttributes($nestedAttributes1);
        $this->assertEquals('yo', $request->getAttributeDeep([0 => 'foo', 1 => 'bar']));

        $nestedAttributes2 = ['foo' => ['bar' => ['yo' => 'yolo']]];
        $request->setAttributes($nestedAttributes2);
        $this->assertEquals('yolo', $request->getAttributeDeep([0 => 'foo', 1 => 'bar', 2 => 'yo']));
    }
}
