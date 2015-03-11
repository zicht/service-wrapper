<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common\Observers;

use \PHPUnit_Framework_TestCase;

use \Zicht\Service\Common\RequestInterface;
use \Zicht\Service\Common\ResponseInterface;
use \Zicht\Service\Common\ServiceCall;
use \Zicht\Service\Common\Cache\CacheAdapter;
use \Zicht\Service\Common\Cache\ArrayMatcher;
use \Zicht\Service\Common\Cache\MemoryStorage;

class CacheTest extends PHPUnit_Framework_TestCase {
    /**
     * @var \Sro\Service\Observers\Cache
     */
    protected $cacheManager;

    function setUp() {
        $this->cache = new \stdClass;
        $this->cacheManager = new Cache(
            new CacheAdapter(
                new ArrayMatcher(array('cachable' => array('default' => null, 'attributes' => array()))),
                new MemoryStorage($this->cache)
            )
        );
    }

    function testCachableResponseIsCancelledSecondTime() {
        $params = array(1, 2, 3);

        // first call will be executed
        $event = new Event(new Request('cachable', $params), new Response());
        $this->cacheManager->notifyBefore($event);

        $event->getResponse()->setResponse($response = rand(0, 9999));
        $fault = null;

        // after call it will be cached
        $this->cacheManager->notifyAfter($event);

        // so the cache must be populated
        $this->assertNotEquals(new \stdClass(), $this->cache);

        // and the next call will be cancelled
        $event = new Event(new Request('cachable', $params), new Response());
        $this->cacheManager->notifyBefore($event);
        $oldResponse = $response;
        $this->assertTrue((bool)$event->isCancelled());

        // which will yield the correct response after executing
        $this->cacheManager->notifyAfter($event);
        $this->assertEquals($oldResponse, $event->getResponse()->getResponse());
    }
}