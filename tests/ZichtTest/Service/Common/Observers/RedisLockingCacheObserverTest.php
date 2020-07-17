<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common\Observers;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\Cache\ArrayMatcher;
use Zicht\Service\Common\Observers\RedisLockingCacheObserver;
use Zicht\Service\Common\Request;
use Zicht\Service\Common\Response;
use Zicht\Service\Common\ServiceCall;
use Zicht\Service\Common\ServiceWrapper;
use Zicht\Service\Common\Storage\RedisStorageFactory;

class RedisLockingCacheObserverTest extends TestCase
{
    /** @var MockObject|\Redis */
    protected $redis;

    /** @var MockObject|ServiceWrapper */
    protected $serviceWrapper;

    /** @var MockObject|RedisStorageFactory */
    protected $redisStorageFactory;

    /** @var MockObject|RedisLockingCacheObserver */
    protected $redisLockingCacheObserver;

    /** @var \stdClass */
    protected $cacheData;

    function setUp()
    {
        $this->redis = $this->getMockBuilder(\Redis::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->redis->method('_serialize')->willReturnArgument(0);
        $this->redisStorageFactory = $this->getMockBuilder(RedisStorageFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->redisStorageFactory->method('getClient')->willReturn($this->redis);
        $this->serviceWrapper = $this->getMockBuilder(ServiceWrapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->redisLockingCacheObserver = $this->getMockBuilder(RedisLockingCacheObserver::class)
            ->setConstructorArgs([$this->redisStorageFactory])
            ->setMethods(['createToken'])
            ->getMock();
        $this->redisLockingCacheObserver->attachRequestMatcher(new ArrayMatcher(['cachable' => ['default' => 123, 'attributes' => []]]));
    }

    /**
     * A request with a cache hit
     *
     * Redis calls made in `notifyBefore`
     * 1. get -> 'data-response'  (cache available)
     */
    function testCacheHit()
    {
        $this->redisLockingCacheObserver->expects($this->never())->method('createToken');
        $this->redis->expects($this->exactly(1))->method('get')->with('cachable')->willReturn('data-response');
        $this->redis->expects($this->never())->method('set');
        $this->redis->expects($this->never())->method('setex');
        $this->redis->expects($this->never())->method('eval');

        // first call will be executed
        $event = new ServiceCall($this->serviceWrapper, new Request('cachable'), new Response());
        $this->redisLockingCacheObserver->notifyBefore($event);
        $this->redisLockingCacheObserver->notifyAfter($event);

        // so the cache must be populated
        $this->assertEquals('data-response', $event->getResponse()->getResponse());

        // and the request must have been cancelled, since it came from the cache
        $this->assertTrue($event->isCancelled());
    }

    /**
     * A request with a cache miss will create a lock to prevent other requests from passing through while cache is being populated
     *
     * Redis calls made in `notifyBefore`
     * 1. get -> false  (cache not available)
     * 2. set -> true   (claim lock)
     * 3. get -> false  (cache still not available)
     *
     * Redis calls made in `notifyAfter`
     * 4. setex -> true (cache write)
     * 5. eval -> 1     (claim unlock)
     */
    function testCacheMissLock()
    {
        $this->redisLockingCacheObserver->expects($this->exactly(1))->method('createToken')->with()->willReturn('token-12345');
        $this->redis->expects($this->exactly(2))->method('get')->with('cachable')->willReturn(false);
        $this->redis->expects($this->exactly(1))->method('set')->with('LOCK::cachable', 'token-12345', ['nx', 'ex' => 5])->willReturn(true);
        $this->redis->expects($this->exactly(1))->method('setex')->with('cachable', 123, 'data-response')->willReturn(true);
        $this->redis->expects($this->exactly(1))->method('eval')->with(RedisLockingCacheObserver::UNLOCK_SCRIPT, ['LOCK::cachable', 'token-12345'], 1)->willReturn(1);

        // first call will be executed
        $event = new ServiceCall($this->serviceWrapper, new Request('cachable'), new Response());
        $this->redisLockingCacheObserver->notifyBefore($event);
        $event->getResponse()->setResponse('data-response');
        $this->redisLockingCacheObserver->notifyAfter($event);

        // so the cache must be populated
        $this->assertEquals('data-response', $event->getResponse()->getResponse());

        // and the request must *not* have been cancelled, since it came from the service
        $this->assertFalse($event->isCancelled());
    }

    /**
     * A request with a cache miss when a lock is active, the request will busy-wait until Redis says the lock is lifted
     *
     * Redis calls made in `notifyBefore`
     * 1. get -> false  (cache not available)
     * 2. set -> false  (claim lock try #1)
     * 3. set -> false  (claim lock retry #2)
     * 4. set -> false  (claim lock retry #3)
     * 5. set -> true   (claim lock retry #4)
     * 6. get -> 'data-response'   (cache available)
     * 7. eval -> 1     (claim unlock)
     */
    function testCacheMissBusyWait()
    {
        $this->redisLockingCacheObserver->expects($this->exactly(1))->method('createToken')->with()->willReturn('token-12345');
        $this->redis->expects($this->exactly(2))->method('get')->with('cachable')->willReturnOnConsecutiveCalls(false, 'data-response');
        $this->redis->expects($this->exactly(4))->method('set')->with('LOCK::cachable', 'token-12345', ['nx', 'ex' => 5])->willReturnOnConsecutiveCalls(false, false, false, true);
        $this->redis->expects($this->exactly(0))->method('setex');
        $this->redis->expects($this->exactly(1))->method('eval')->with(RedisLockingCacheObserver::UNLOCK_SCRIPT, ['LOCK::cachable', 'token-12345'], 1)->willReturn(1);

        // first call will be executed
        $event = new ServiceCall($this->serviceWrapper, new Request('cachable'), new Response());
        $this->redisLockingCacheObserver->notifyBefore($event);
        $this->redisLockingCacheObserver->notifyAfter($event);

        // so the cache must be populated
        $this->assertEquals('data-response', $event->getResponse()->getResponse());

        // and the request must have been cancelled, since it came from the cache
        $this->assertTrue($event->isCancelled());
    }
}
