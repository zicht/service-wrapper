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
            ->getMock();
        $this->redisStorageFactory = $this->getMockBuilder(RedisStorageFactory::class)
            ->disableOriginalConstructor()
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
        $this->redis->expects($this->exactly(2))->method('get')->with('cachable')->willReturnOnConsecutiveCalls(false, 'data-response');
        $this->redis->expects($this->exactly(1))->method('setex')->with('cachable', 123, 'data-response')->willReturn(true);

        // first call will be executed
        $call = new ServiceCall($this->serviceWrapper, new Request('cachable'), new Response());
        $this->redisCacheObserver->notifyBefore($call);
        $call->getResponse()->setResponse('data-response');
        $this->redisCacheObserver->notifyAfter($call);

        // so the cache must be populated
        $this->assertEquals('data-response', $call->getResponse()->getResponse());

        // and the next call will be cancelled
        $call = new ServiceCall($this->serviceWrapper, new Request('cachable'), new Response());
        $this->redisCacheObserver->notifyBefore($call);
        $this->assertTrue($call->isCancelled());

        // which will yield the correct response after executing
        $this->redisCacheObserver->notifyAfter($call);
        $this->assertEquals('data-response', $call->getResponse()->getResponse());
    }
}
