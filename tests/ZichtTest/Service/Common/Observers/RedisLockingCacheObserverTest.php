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
        $this->redisLockingCacheObserver->attachRequestMatcher(
            new ArrayMatcher(
                [
                    'value_cachable' => ['fallback' => ['value' => 10, 'error' => 0, 'grace' => 0], 'attributes' => []],
                    'error_cachable' => ['fallback' => ['value' => 10, 'error' => 15, 'grace' => 0], 'attributes' => []],
                    'grace_cachable' => ['fallback' => ['value' => 10, 'error' => 0, 'grace' => 20], 'attributes' => []],
                ]
            )
        );
    }

    /**
     * A request with a cache hit
     *
     * Redis calls made in `notifyBefore`
     * 1. get -> 'data-response'    (cache available)
     */
    function testCacheHit()
    {
        $this->redisLockingCacheObserver->expects($this->never())->method('createToken');
        $this->serviceWrapper->expects($this->never())->method('__call');
        $this->redis->expects($this->exactly(1))->method('get')->with('value_cachable:[]')->willReturn(['g' => 0, 'e' => null, 'v' => 'data-response']);
        $this->redis->expects($this->never())->method('set');
        $this->redis->expects($this->never())->method('setex');
        $this->redis->expects($this->never())->method('eval');
        $this->redis->expects($this->never())->method('ttl');

        // first call will be executed
        $call = new ServiceCall($this->serviceWrapper, new Request('value_cachable'), new Response());
        $this->redisLockingCacheObserver->notifyBefore($call);
        $this->redisLockingCacheObserver->notifyAfter($call);
        $this->redisLockingCacheObserver->terminate();

        // so the cache must be populated
        $this->assertEquals('data-response', $call->getResponse()->getResponse());

        // and the request must have been cancelled, since it came from the cache
        $this->assertTrue($call->isCancelled());
    }

    /**
     * A request with a cache miss will create a lock to prevent other requests from passing through while cache is being populated
     *
     * Redis calls made in `notifyBefore`
     * 1. get -> false              (cache not available)
     * 2. set -> true               (claim lock)
     * 3. get -> false              (cache still not available)
     *
     * Redis calls made in `notifyAfter`
     * 4. setex -> true             (cache write)
     * 5. eval -> 1                 (claim unlock)
     */
    function testCacheMissLock()
    {
        $this->redisLockingCacheObserver->expects($this->exactly(1))->method('createToken')->with()->willReturn('token-12345');
        $this->serviceWrapper->expects($this->never())->method('__call');
        $this->redis->expects($this->exactly(2))->method('get')->with('value_cachable:[]')->willReturn(false);
        $this->redis->expects($this->exactly(1))->method('set')->with('LOCK::value_cachable:[]', 'token-12345', ['nx', 'ex' => 3])->willReturn(true);
        $this->redis->expects($this->exactly(1))->method('setex')->with('value_cachable:[]', 10, ['g' => 0, 'e' => null, 'v' => 'data-response'])->willReturn(true);
        $this->redis->expects($this->exactly(1))->method('eval')->with(RedisLockingCacheObserver::UNLOCK_SCRIPT, ['LOCK::value_cachable:[]', 'token-12345'], 1)->willReturn(1);
        $this->redis->expects($this->never())->method('ttl');

        // first call will be executed
        $call = new ServiceCall($this->serviceWrapper, new Request('value_cachable'), new Response());
        $this->redisLockingCacheObserver->notifyBefore($call);
        $call->getResponse()->setResponse('data-response');
        $this->redisLockingCacheObserver->notifyAfter($call);
        $this->redisLockingCacheObserver->terminate();

        // so the cache must be populated
        $this->assertEquals('data-response', $call->getResponse()->getResponse());

        // and the request must *not* have been cancelled, since it came from the service
        $this->assertFalse($call->isCancelled());
    }

    /**
     * A request with a cache miss when a lock is active, the request will busy-wait until Redis says the lock is lifted
     *
     * Redis calls made in `notifyBefore`
     * 1. get -> false              (cache not available)
     * 2. set -> false              (claim lock try #1)
     * 3. set -> false              (claim lock retry #2)
     * 4. set -> false              (claim lock retry #3)
     * 5. set -> true               (claim lock retry #4)
     * 6. get -> 'data-response'    (cache available)
     * 7. eval -> 1                 (claim unlock)
     */
    function testCacheMissBusyWait()
    {
        $this->redisLockingCacheObserver->expects($this->exactly(1))->method('createToken')->with()->willReturn('token-12345');
        $this->serviceWrapper->expects($this->never())->method('__call');
        $this->redis->expects($this->exactly(2))->method('get')->with('value_cachable:[]')->willReturnOnConsecutiveCalls(false, ['g' => 0, 'e' => null, 'v' => 'data-response']);
        $this->redis->expects($this->exactly(4))->method('set')->with('LOCK::value_cachable:[]', 'token-12345', ['nx', 'ex' => 3])->willReturnOnConsecutiveCalls(false, false, false, true);
        $this->redis->expects($this->exactly(0))->method('setex');
        $this->redis->expects($this->exactly(1))->method('eval')->with(RedisLockingCacheObserver::UNLOCK_SCRIPT, ['LOCK::value_cachable:[]', 'token-12345'], 1)->willReturn(1);
        $this->redis->expects($this->never())->method('ttl');

        // first call will be executed
        $call = new ServiceCall($this->serviceWrapper, new Request('value_cachable'), new Response());
        $this->redisLockingCacheObserver->notifyBefore($call);
        $this->redisLockingCacheObserver->notifyAfter($call);
        $this->redisLockingCacheObserver->terminate();

        // so the cache must be populated
        $this->assertEquals('data-response', $call->getResponse()->getResponse());

        // and the request must have been cancelled, since it came from the cache
        $this->assertTrue($call->isCancelled());
    }

    /**
     * A request with a cache hit and the grace check determines no refresh is needed
     *
     * Redis calls made in `notifyBefore`
     * 1. get -> 'data-response'    (cache available)
     *
     * Redis calls made in the `terminate`
     * 2. ttl -> $ttlResponse       (outside >10s ttl window)
     *
     * @dataProvider noGraceRefreshTtlProvider
     */
    function testGraceCacheHitWithoutGraceRefresh($ttlResponse)
    {
        $this->redisLockingCacheObserver->expects($this->never())->method('createToken');
        $this->serviceWrapper->expects($this->never())->method('__call');
        $this->redis->expects($this->exactly(1))->method('get')->with('grace_cachable:[]')->willReturn(['g' => 20, 'e' => null, 'v' => 'data-response']);
        $this->redis->expects($this->exactly(1))->method('ttl')->with('grace_cachable:[]')->willReturn($ttlResponse);
        $this->redis->expects($this->never())->method('set');
        $this->redis->expects($this->never())->method('setex');
        $this->redis->expects($this->never())->method('eval');

        // first call will be executed
        $call = new ServiceCall($this->serviceWrapper, new Request('grace_cachable'), new Response());
        $this->redisLockingCacheObserver->notifyBefore($call);
        $this->redisLockingCacheObserver->notifyAfter($call);
        $this->redisLockingCacheObserver->terminate();

        // so the cache must be populated
        $this->assertEquals('data-response', $call->getResponse()->getResponse());

        // and the request must have been cancelled, since it came from the cache
        $this->assertTrue($call->isCancelled());
    }

    /**
     * Returns TTL values that should return in 'no grace refresh' given a grace configured at 20 seconds
     *
     * @return array
     */
    function noGraceRefreshTtlProvider()
    {
        return [
            ['ttlResponse' => 20],
            ['ttlResponse' => 21],
            ['ttlResponse' => 3600],
        ];
    }

    /**
     * A request with a cache hit and the grace check determines refresh is needed but the lock determines that another process is already refreshing it
     *
     * Redis calls made in `notifyBefore`
     * 1. get -> 'data-response'    (cache available)
     *
     * Redis calls made in the `terminate`
     * 2. ttl -> $ttlResponse       (outside <10s ttl window)
     *
     * Redis calls made in terminating `notifyBefore`
     * 3. set -> false              (lock unavailable)
     *
     * @dataProvider graceRefreshTtlProvider
     */
    function testGraceCacheHitWithGraceCheckWithoutRefresh($ttlResponse)
    {
        $this->redisLockingCacheObserver->expects($this->exactly(1))->method('createToken')->with()->willReturn('token-12345');
        $this->redis->expects($this->exactly(1))->method('get')->with('grace_cachable:[]')->willReturn(['g' => 20, 'e' => null, 'v' => 'data-response']);
        $this->redis->expects($this->exactly(1))->method('ttl')->with('grace_cachable:[]')->willReturn($ttlResponse);
        $this->redis->expects($this->exactly(1))->method('set')->with('LOCK::grace_cachable:[]', 'token-12345', ['nx', 'ex' => 3])->willReturn(false);
        $this->redis->expects($this->never())->method('setex');
        $this->redis->expects($this->never())->method('eval');

        $this->serviceWrapper
            ->expects($this->exactly(1))
            ->method('__call')->with('grace_cachable', [])->willReturnCallback(
                function ($methodName, $args) {
                    $call = new ServiceCall($this->serviceWrapper, new Request($methodName, $args), new Response(), null, true);
                    try {
                        $this->redisLockingCacheObserver->notifyBefore($call);
                    } catch (RedisCacheTerminateException $exception) {
                        throw $exception;
                    }
                    $this->fail('Should not reach this point');
                }
            );


        // first call will be executed
        $call = new ServiceCall($this->serviceWrapper, new Request('grace_cachable'), new Response());
        $this->redisLockingCacheObserver->notifyBefore($call);
        $this->redisLockingCacheObserver->notifyAfter($call);
        $this->redisLockingCacheObserver->terminate();

        // so the cache must be populated
        $this->assertEquals('data-response', $call->getResponse()->getResponse());

        // and the request must have been cancelled, since it came from the cache
        $this->assertTrue($call->isCancelled());
    }

    /**
     * A request with a cache hit and the grace check determines refresh is needed
     *
     * Redis calls made in `notifyBefore`
     * 1. get -> 'data-response'    (cache available)
     *
     * Redis calls made in the `terminate`
     * 2. ttl -> $ttlResponse       (outside <10s ttl window)
     *
     * Redis calls made in terminating `notifyBefore`
     * 3. set -> true               (lock available)
     * 4. ttl -> $ttlResponse       (outside <10s ttl window)
     *
     * Redis calls made in terminating `notifyAfter`
     * 5. setex -> true             (cache write)
     * 6. eval -> 1                 (claim unlock)
     *
     * @dataProvider graceRefreshTtlProvider
     */
    function testGraceCacheHitWithGraceCheckWithRefresh($ttlResponse)
    {
        $this->redisLockingCacheObserver->expects($this->exactly(1))->method('createToken')->with()->willReturn('token-12345');
        $this->redis->expects($this->exactly(1))->method('get')->with('grace_cachable:[]')->willReturn(['g' => 20, 'e' => null, 'v' => 'data-response']);
        $this->redis->expects($this->exactly(2))->method('ttl')->with('grace_cachable:[]')->willReturn($ttlResponse);
        $this->redis->expects($this->exactly(1))->method('set')->with('LOCK::grace_cachable:[]', 'token-12345', ['nx', 'ex' => 3])->willReturn(true);
        $this->redis->expects($this->exactly(1))->method('setex')->with('grace_cachable:[]', 10 + 20, ['g' => 20, 'e' => null, 'v' => 'data-response'])->willReturn(true);
        $this->redis->expects($this->exactly(1))->method('eval')->with(RedisLockingCacheObserver::UNLOCK_SCRIPT, ['LOCK::grace_cachable:[]', 'token-12345'], 1)->willReturn(1);

        $this->serviceWrapper
            ->expects($this->exactly(1))
            ->method('__call')->with('grace_cachable', [])->willReturnCallback(
                function ($methodName, $args) {
                    $call = new ServiceCall($this->serviceWrapper, new Request($methodName, $args), new Response(), null, true);
                    $this->redisLockingCacheObserver->notifyBefore($call);
                    $call->getResponse()->setResponse('data-response');
                    $this->redisLockingCacheObserver->notifyAfter($call);

                    // so the cache must be populated
                    $this->assertEquals('data-response', $call->getResponse()->getResponse());

                    // the request must have been cancelled, because the lock was not aquired
                    $this->assertFalse($call->isCancelled());
                }
            );


        // first call will be executed
        $call = new ServiceCall($this->serviceWrapper, new Request('grace_cachable'), new Response());
        $this->redisLockingCacheObserver->notifyBefore($call);
        $this->redisLockingCacheObserver->notifyAfter($call);
        $this->redisLockingCacheObserver->terminate();

        // so the cache must be populated
        $this->assertEquals('data-response', $call->getResponse()->getResponse());

        // and the request must have been cancelled, since it came from the cache
        $this->assertTrue($call->isCancelled());
    }

    /**
     * A request with a cache hit and the grace check determines refresh is needed
     *
     * Redis calls made in `notifyBefore`
     * 1. get -> 'data-response'    (cache available)
     *
     * Redis calls made in the `terminate`
     * 2. ttl -> $ttlResponse       (outside <10s ttl window)
     *
     * Redis calls made in terminating `notifyBefore`
     * 3. set -> true               (lock available)
     * 4. ttl -> 3600               (ttl is very high, i.e. data has already been fetched)
     * 6. eval -> 1                 (claim unlock)
     *
     * @dataProvider graceRefreshTtlProvider
     */
    function testGraceCacheHitWithGraceCheckWithLateIgnore($ttlResponse)
    {
        $this->redisLockingCacheObserver->expects($this->exactly(1))->method('createToken')->with()->willReturn('token-12345');
        $this->redis->expects($this->exactly(1))->method('get')->with('grace_cachable:[]')->willReturn(['g' => 20, 'e' => null, 'v' => 'data-response']);
        $this->redis->expects($this->exactly(2))->method('ttl')->with('grace_cachable:[]')->willReturnOnConsecutiveCalls($ttlResponse, 3600);
        $this->redis->expects($this->exactly(1))->method('set')->with('LOCK::grace_cachable:[]', 'token-12345', ['nx', 'ex' => 3])->willReturn(true);
        $this->redis->expects($this->exactly(1))->method('eval')->with(RedisLockingCacheObserver::UNLOCK_SCRIPT, ['LOCK::grace_cachable:[]', 'token-12345'], 1)->willReturn(1);
        $this->redis->expects($this->never())->method('setex');

        $this->serviceWrapper
            ->expects($this->exactly(1))
            ->method('__call')->with('grace_cachable', [])->willReturnCallback(
                function ($methodName, $args) {
                    $call = new ServiceCall($this->serviceWrapper, new Request($methodName, $args), new Response(), null, true);
                    $this->redisLockingCacheObserver->notifyBefore($call);
                    $call->getResponse()->setResponse('data-response');
                    $this->redisLockingCacheObserver->notifyAfter($call);

                    // so the cache must be populated
                    $this->assertEquals('data-response', $call->getResponse()->getResponse());

                    // the request must have been cancelled, because the lock was not aquired
                    $this->assertFalse($call->isCancelled());
                }
            );


        // first call will be executed
        $call = new ServiceCall($this->serviceWrapper, new Request('grace_cachable'), new Response());
        $this->redisLockingCacheObserver->notifyBefore($call);
        $this->redisLockingCacheObserver->notifyAfter($call);
        $this->redisLockingCacheObserver->terminate();

        // so the cache must be populated
        $this->assertEquals('data-response', $call->getResponse()->getResponse());

        // and the request must have been cancelled, since it came from the cache
        $this->assertTrue($call->isCancelled());
    }

    /**
     * Returns TTL values that should return in 'no grace refresh' given a grace configured at 66 seconds
     *
     * @return array
     */
    function graceRefreshTtlProvider()
    {
        return [
            // Boundary checks for the configured 20s grace
            ['ttlResponse' => 19],
            ['ttlResponse' => 18],

            // Boundary checks for the configured 10s ttl
            ['ttlResponse' => 11],
            ['ttlResponse' => 10],
            ['ttlResponse' => 9],

            // Boundary checks for approaching cache timeout
            ['ttlResponse' => 1],
            ['ttlResponse' => 0],

            // Check when redis says that the cache no longer exists
            ['ttlResponse' => false],

            // Check what would happen on negative value (this is theoretically impossible)
            ['ttlResponse' => -100],
        ];
    }
}
