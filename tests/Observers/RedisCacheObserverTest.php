<?php declare(strict_types=1);
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

    public function setUp(): void
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
        $this->redisCacheObserver->attachRequestMatcher(new ArrayMatcher([
            'value_cachable' => ['fallback' => ['value' => 10, 'error' => 0, 'grace' => 0], 'attributes' => []],
            'error_cachable' => ['fallback' => ['value' => 10, 'error' => 15, 'grace' => 0], 'attributes' => []],
        ]));
        $this->cacheData = new \stdClass();
    }

    public function testCachableResponseIsCancelledSecondTime()
    {
        $this->redis->expects($this->exactly(2))->method('get')->with('value_cachable:[]')->willReturnOnConsecutiveCalls(false, ['g' => 0, 'e' => null, 'v' => 'data-response']);
        $this->redis->expects($this->exactly(1))->method('setex')->with('value_cachable:[]', 10, ['g' => 0, 'e' => null, 'v' => 'data-response'])->willReturn(true);

        // first call will be executed
        $call = new ServiceCall($this->serviceWrapper, new Request('value_cachable'), new Response());
        $this->redisCacheObserver->notifyBefore($call);
        $call->getResponse()->setResponse('data-response');
        $this->redisCacheObserver->notifyAfter($call);

        // so the cache must be populated
        $this->assertEquals(null, $call->getResponse()->getError());
        $this->assertEquals('data-response', $call->getResponse()->getResponse());

        // and the next call will be cancelled
        $call = new ServiceCall($this->serviceWrapper, new Request('value_cachable'), new Response());
        $this->redisCacheObserver->notifyBefore($call);
        $this->assertTrue($call->isCancelled());

        // which will yield the correct response after executing
        $this->redisCacheObserver->notifyAfter($call);
        $this->assertEquals(null, $call->getResponse()->getError());
        $this->assertEquals('data-response', $call->getResponse()->getResponse());
    }

    // todo: test with grace

    public function testCachableErrorIsCancelledSecondTime()
    {
        $this->redis->expects($this->exactly(2))->method('get')->with('error_cachable:[]')->willReturnOnConsecutiveCalls(false, ['g' => 0, 'e' => new \Exception('Fail'), 'v' => null]);
        $this->redis->expects($this->exactly(1))->method('setex')->with('error_cachable:[]', 15, ['g' => 0, 'e' => new \Exception('Fail'), 'v' => null])->willReturn(true);

        // first call will be executed
        $call = new ServiceCall($this->serviceWrapper, new Request('error_cachable'), new Response());
        $this->redisCacheObserver->notifyBefore($call);
        $call->getResponse()->setError(new \Exception('Fail'));
        $this->redisCacheObserver->notifyAfter($call);

        // so the cache must be populated
        $this->assertEquals(new \Exception('Fail'), $call->getResponse()->getError());
        $this->assertEquals(null, $call->getResponse()->getResponse());

        // and the next call will be cancelled
        $call = new ServiceCall($this->serviceWrapper, new Request('error_cachable'), new Response());
        $this->redisCacheObserver->notifyBefore($call);
        $this->assertTrue($call->isCancelled());

        // which will yield the correct response after executing
        $this->redisCacheObserver->notifyAfter($call);
        $this->assertEquals(new \Exception('Fail'), $call->getResponse()->getError());
        $this->assertEquals(null, $call->getResponse()->getResponse());
    }
}
