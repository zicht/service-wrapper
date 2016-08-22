<?php

namespace ZichtTest\Service\Common;

use PHPUnit_Framework_TestCase;
use Zicht\Service\Common\Response;

class ResponseTest extends PHPUnit_Framework_TestCase
{
    function testGetPropertyDeep()
    {
        $value = rand(1, 9999);
        $response = new Response(array(array('a' => array('b' => array('c' => $value)))));
        $this->assertEquals($value, $response->getPropertyDeep(array(0, 'a', 'b', 'c')));
    }

    function testGetPropertyDeepObjects()
    {
        $value = rand(1, 9999);
        $response = new Response(array((object)array('a' => (object)array('b' => (object)array('c' => $value)))));
        $this->assertEquals($value, $response->getPropertyDeep(array(0, 'a', 'b', 'c')));
    }

    function testGetPropertiesDeep()
    {
        $expected = array(
            array(array('a', 0, 'c'), rand(1, 9999)),
            array(array('a', 1, 'c'), rand(1, 9999)),
        );
        $response = new Response(
            (object)array(
                'a' => array(
                    (object)array('c' => $expected[0][1]),
                    (object)array('c' => $expected[1][1]),
                )
            )
        );
        $this->assertEquals($expected, $response->getPropertiesDeep(array('a[]', 'c')));
    }

    function testGetPropertiesDeeper()
    {
        $expected = array(
            array(array('a', 0, 'b', 0, 'c'), rand(1, 9999)),
            array(array('a', 0, 'b', 1, 'c'), rand(1, 9999)),
            array(array('a', 1, 'b', 0, 'c'), rand(1, 9999)),
            array(array('a', 1, 'b', 1, 'c'), rand(1, 9999)),
        );
        $response = new Response(
            (object)array(
                'a' => array(
                    (object)array(
                        'b' => array(
                            (object)array('c' => $expected[0][1]),
                            (object)array('c' => $expected[1][1]),
                        )
                    ),
                    (object)array(
                        'b' => array(
                            (object)array('c' => $expected[2][1]),
                            (object)array('c' => $expected[3][1]),
                        )
                    )
                )
            )
        );
        $this->assertEquals($expected, $response->getPropertiesDeep(array('a[]', 'b[]', 'c')));
    }

    function testGetPropertyDeepIfValueNotSet()
    {
        $response = new Response(
            (object)array(
                'a' => array(
                    (object)array('c' => rand(1, 9999)),
                    (object)array('c' => rand(1, 9999)),
                ),
            )
        );
        $this->assertEquals(array(), $response->getPropertiesDeep(array('a[]', 'x')));
        $this->assertEquals(array(), $response->getPropertiesDeep(array('a[]', 'c[]')));
        $this->assertEquals(array(), $response->getPropertiesDeep(array('x', 'a[]')));
        $this->assertEquals(array(), $response->getPropertiesDeep(array('x[]', 'a')));
    }

    function testSetPropertyDeep()
    {
        $value = rand(1, 9999);
        $value2 = rand(10000, 19999);
        $response = new Response(array(array('a' => array('b' => array('c' => $value)))));
        $response->setPropertyDeep(array(0, 'a', 'b', 'c'), $value2);
        $this->assertEquals($value2, $response->getPropertyDeep(array(0, 'a', 'b', 'c')));
    }

    function testSetPropertyDeepIfValueNotPreviouslySet()
    {
        $value2 = rand(10000, 19999);
        $response = new Response(array());
        $response->setPropertyDeep(array(0, 'a', 'b', 'c'), $value2);
        $this->assertEquals($value2, $response->getPropertyDeep(array(0, 'a', 'b', 'c')));
    }

    function testSetPropertyDeepIfValueNotPreviouslySetOnObject()
    {
        $value2 = rand(10000, 19999);
        $response = new Response(array(['a' => (object) ['b' => 'foo']]));
        $response->setPropertyDeep(array(0, 'a', 'c'), $value2);
        $this->assertEquals($value2, $response->getPropertyDeep(array(0, 'a', 'c')));
    }

    function testSetCachable()
    {
        $response = new Response();

        // by default, all responses are cachable, until specified otherwise.
        $this->assertTrue($response->isCachable());
        $response->setCachable(true);
        $this->assertTrue($response->isCachable());

        $response->setCachable(false);
        $this->assertFalse($response->isCachable());
    }

    /**
     * @dataProvider supportedErrors
     */
    function testSetError($error)
    {
        $response = new Response();
        $this->assertFalse($response->isError());

        $response->setError($error);
        $this->assertTrue($response->isError());

        $this->assertEquals($error, $response->getError());
    }
    public function supportedErrors()
    {
        return [
            ["error as string"],
            [new \Exception("error as exception")]
        ];
    }

    function testSetResponse()
    {
        $response = new Response();
        $this->assertNull($response->getResponse());

        $response->setResponse(['a' => 'b']);
        $this->assertEquals(['a' => 'b'], $response->getResponse());

        $response->setPropertyDeep(['a'], 'X');
        $this->assertEquals(['a' => 'X'], $response->getResponse());
    }


    function testStringRepresentation()
    {
        $response = new Response();
        $response->setResponse(['prop' => 'value']);

        $this->assertRegExp('/prop/', (string)$response);
        $this->assertRegExp('/value/', (string)$response);
    }
}