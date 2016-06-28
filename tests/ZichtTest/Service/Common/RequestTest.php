<?php

namespace ZichtTest\Service\Common;

use PHPUnit_Framework_TestCase;
use Zicht\Service\Common\Request;

class RequestTest extends PHPUnit_Framework_TestCase
{
    function testGetParameterDeep()
    {
        $value = rand(1, 9999);
        $request = new Request('', array(array('a' => array('b' => array('c' => $value)))));
        $this->assertEquals($value, $request->getParameterDeep(array(0, 'a', 'b', 'c')));
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
}