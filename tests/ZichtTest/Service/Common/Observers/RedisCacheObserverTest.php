<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common\Observers;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\Cache\ArrayMatcher;
use Zicht\Service\Common\Observers\RedisCacheObserver;
use Zicht\Service\Common\Request;
use Zicht\Service\Common\Response;
use Zicht\Service\Common\ServiceCall;
use Zicht\Service\Common\ServiceWrapper;
use Zicht\Service\Common\Storage\RedisStorageFactory;

/**
 * @covers \Zicht\Service\Common\Observers\Cache
 */
class RedisCacheObserverTest extends TestCase
{
    /** @var MockObject|\Redis */
    protected $redis;

    /** @var MockObject|ServiceWrapper */
    protected $serviceWrapper;

    /** @var MockObject|RedisStorageFactory */
    protected $redisStorageFactory;

    /** @var MockObject|RedisCacheObserver */
    protected $redisCacheObserver;

    /** @var \stdClass */
    protected $cacheData;

    function setUp()
    {
        $this->redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->setMethods(['get', 'setex'])
            ->getMock();
        $this->redisStorageFactory = $this->getMockBuilder(RedisStorageFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['getClient'])
            ->getMock();
        $this->redisStorageFactory->method('getClient')->willReturn($this->redis);
        $this->serviceWrapper = $this->getMockBuilder(ServiceWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->redisCacheObserver = new RedisCacheObserver($this->redisStorageFactory);
        $this->redisCacheObserver->attachRequestMatcher(new ArrayMatcher(['cachable' => ['default' => 123, 'attributes' => []]]));
        $this->cacheData = new \stdClass;
    }

    /**
     * The second time that the a request with the same cache key is called, the second response is retrieved from cache
     */
    function testCachableResponseIsCancelledSecondTime()
    {
        $this->redis->expects($this->exactly(2))->method('get')->willReturnOnConsecutiveCalls(false, 'data-response');
        $this->redis->expects($this->exactly(1))->method('setex')->with('cachable', 123, 'data-response')->willReturnOnConsecutiveCalls(true);

        // first call will be executed
        $event = new ServiceCall($this->serviceWrapper, new Request('cachable'), new Response());
        $this->redisCacheObserver->notifyBefore($event);
        $event->getResponse()->setResponse('data-response');
        $this->redisCacheObserver->notifyAfter($event);

        // so the cache must be populated
        $this->assertEquals('data-response', $event->getResponse()->getResponse());

        // and the next call will be cancelled
        $event = new ServiceCall($this->serviceWrapper, new Request('cachable'), new Response());
        $this->redisCacheObserver->notifyBefore($event);
        $this->assertTrue($event->isCancelled());

        // which will yield the correct response after executing
        $this->redisCacheObserver->notifyAfter($event);
        $this->assertEquals('data-response', $event->getResponse()->getResponse());
    }
}
