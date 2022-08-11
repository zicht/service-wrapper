<?php declare(strict_types=1);

namespace ZichtTest\Service\Common;

use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\Response;

class ResponseTest extends TestCase
{
    public function testGetPropertyDeep()
    {
        $value = rand(1, 9999);
        $response = new Response([['a' => ['b' => ['c' => $value]]]]);
        $this->assertEquals($value, $response->getPropertyDeep([0, 'a', 'b', 'c']));
    }

    public function testGetPropertyDeepObjects()
    {
        $value = rand(1, 9999);
        $response = new Response([(object)['a' => (object)['b' => (object)['c' => $value]]]]);
        $this->assertEquals($value, $response->getPropertyDeep([0, 'a', 'b', 'c']));
    }

    public function testGetPropertiesDeep()
    {
        $expected = [
            [['a', 0, 'c'], rand(1, 9999)],
            [['a', 1, 'c'], rand(1, 9999)],
        ];
        $response = new Response(
            (object)[
                'a' => [
                    (object)['c' => $expected[0][1]],
                    (object)['c' => $expected[1][1]],
                ],
            ]
        );
        $this->assertEquals($expected, $response->getPropertiesDeep(['a[]', 'c']));
    }

    public function testGetPropertiesDeeper()
    {
        $expected = [
            [['a', 0, 'b', 0, 'c'], rand(1, 9999)],
            [['a', 0, 'b', 1, 'c'], rand(1, 9999)],
            [['a', 1, 'b', 0, 'c'], rand(1, 9999)],
            [['a', 1, 'b', 1, 'c'], rand(1, 9999)],
        ];
        $response = new Response(
            (object)[
                'a' => [
                    (object)[
                        'b' => [
                            (object)['c' => $expected[0][1]],
                            (object)['c' => $expected[1][1]],
                        ],
                    ],
                    (object)[
                        'b' => [
                            (object)['c' => $expected[2][1]],
                            (object)['c' => $expected[3][1]],
                        ],
                    ],
                ],
            ]
        );
        $this->assertEquals($expected, $response->getPropertiesDeep(['a[]', 'b[]', 'c']));
    }

    public function testGetPropertyDeepIfValueNotSet()
    {
        $response = new Response(
            (object)[
                'a' => [
                    (object)['c' => rand(1, 9999)],
                    (object)['c' => rand(1, 9999)],
                ],
            ]
        );
        $this->assertEquals([], $response->getPropertiesDeep(['a[]', 'x']));
        $this->assertEquals([], $response->getPropertiesDeep(['a[]', 'c[]']));
        $this->assertEquals([], $response->getPropertiesDeep(['x', 'a[]']));
        $this->assertEquals([], $response->getPropertiesDeep(['x[]', 'a']));
    }

    public function testSetPropertyDeep()
    {
        $value = rand(1, 9999);
        $value2 = rand(10000, 19999);
        $response = new Response([['a' => ['b' => ['c' => $value]]]]);
        $response->setPropertyDeep([0, 'a', 'b', 'c'], $value2);
        $this->assertEquals($value2, $response->getPropertyDeep([0, 'a', 'b', 'c']));
    }

    public function testSetPropertyDeepIfValueNotPreviouslySet()
    {
        $value2 = rand(10000, 19999);
        $response = new Response([]);
        $response->setPropertyDeep([0, 'a', 'b', 'c'], $value2);
        $this->assertEquals($value2, $response->getPropertyDeep([0, 'a', 'b', 'c']));
    }

    public function testSetPropertyDeepIfValueNotPreviouslySetOnObject()
    {
        $value2 = rand(10000, 19999);
        $response = new Response([['a' => (object)['b' => 'foo']]]);
        $response->setPropertyDeep([0, 'a', 'c'], $value2);
        $this->assertEquals($value2, $response->getPropertyDeep([0, 'a', 'c']));
    }

    public function testSetCachable()
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
     * @param mixed $error
     */
    public function testSetError($error)
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
            ['error as string'],
            [new \Exception('error as exception')],
        ];
    }

    public function testSetResponse()
    {
        $response = new Response();
        $this->assertNull($response->getResponse());

        $response->setResponse(['a' => 'b']);
        $this->assertEquals(['a' => 'b'], $response->getResponse());

        $response->setPropertyDeep(['a'], 'X');
        $this->assertEquals(['a' => 'X'], $response->getResponse());
    }

    public function testStringRepresentation()
    {
        $response = new Response();
        $response->setResponse(['prop' => 'value']);
        $this->assertEquals('{"prop":"value"}', (string)$response);
    }
}
