<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */
namespace ZichtTest\Service\Common\Caching;

use \PHPUnit_Framework_TestCase;

use \Zicht\Service\Common\Request;
use \Zicht\Service\Common\Response;
use \Zicht\Service\Common\Cache\CacheAdapter;
use \Zicht\Service\Common\Cache\RequestMatcher;

/**
 * @covers Zicht\Service\Common\Cache\CacheAdapter
 * @covers Zicht\Service\Common\Cache\CacheConfiguration
 */
class CacheAdapterTest extends PHPUnit_Framework_TestCase {
    /** @var \Sro\Service\Caching\CacheAdapter */
    public $cache;
    public $matcher;
    public $backend;

    function setUp() {
        $this->matcher = $this->getMock(
            'Zicht\Service\Common\Cache\RequestMatcher',
            array(
                'isMatch',
                'isExpunger',
                'getKey',
                'getTtl'
            )
        );
        $this->backend = $this->getMock(
            'Zicht\Service\Common\Cache\Storage',
            array(
                'read',
                'write',
                'isValid',
                'invalidate',
                'getKeys'
            )
        );
        $this->cache = new CacheAdapter($this->matcher, $this->backend);
    }


    function testReadWillReadRequestMatcherKeyFromBackend()
    {
        $key = (string) rand(1, 9999);
        $data = (string) rand(10000, 99999);
        $req = new Request('someMethod');
        $this->backend->expects($this->once())->method('read')->with($key)->will($this->returnValue($data));
        $this->matcher->expects($this->once())->method('getKey')->with($req)->will($this->returnValue($key));

        $this->assertEquals($data, $this->cache->read($req));
    }


    function testWriteWillWriteResponseToBackendWithRequestMatcherSpecifiedKeyAndTtl() {
        $ttl = (int) rand(0, 9);
        $key = (string) rand(10, 9999);
        $data = (string) rand(10000, 99999);
        $req = new Request('someMethod');
        $res = new Response($data );
        $this->matcher->expects($this->once())->method('getTtl')->with($req)->will($this->returnValue($ttl));
        $this->backend->expects($this->once())->method('write')->with($key, $res->getResponse(), $ttl);
        $this->matcher->expects($this->once())->method('getKey')->with($req)->will($this->returnValue($key));
        $this->cache->write($req, $res);
    }


    function testIsCachableDelegatesToMatcher() {
        $request = new Request('method');
        $this->matcher->expects($this->once())->method('isMatch')->with($request);
        $this->cache->isCachable($request);
    }


    /**
     * @dataProvider trueAndFalse
     */
    function testIsValidDelegatesToStorageWithMatchersSpecifiedKeyAndTtl($isValid) {
        $ttl = (int) rand(0, 9);
        $key = (string) rand(10, 9999);
        $data = (string) rand(10000, 99999);
        $req = new Request('someMethod');
        $this->matcher->expects($this->once())->method('getTtl')->with($req)->will($this->returnValue($ttl));
        $this->backend->expects($this->once())->method('isValid')->with($key, $ttl)->will($this->returnValue($isValid));
        $this->matcher->expects($this->once())->method('getKey')->with($req)->will($this->returnValue($key));
        $this->assertEquals($isValid, $this->cache->isValid($req));
    }
    function trueAndFalse() {
        return array(
            array(true),
            array(false)
        );
    }


    function testExpunge() {
        $this->backend->expects($this->once())->method('getKeys')->will($this->returnValue(array(
            1, 2, 3
        )));
        $this->backend->expects($this->at(1))->method('invalidate')->with(1);
        $this->backend->expects($this->at(2))->method('invalidate')->with(3);

        $ret = $this->cache->expunge(function($key) { return ($key % 2) === 1; });
        $this->assertEquals(2, $ret);
    }


    function testIsCachableWillTriggerExpunge() {
        $this->backend->expects($this->once())->method('getKeys')->will($this->returnValue(array(
            1, 2, 3
        )));
        $this->matcher->expects($this->once())->method('isExpunger')->will($this->returnValue(true));
        $this->cache->isCachable(new Request('foo'));
    }

    function testToString() {
        return strlen((string) $this->cache) > 0;
    }
}