<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use Zicht\Service\Common\Cache\RequestMatcher;
use Zicht\Service\Common\RequestInterface;
use Zicht\Service\Common\ServiceCallInterface;
use Zicht\Service\Common\Storage\RedisStorageFactory;

/**
 * Class RedisCacheObserver
 */
class RedisCacheObserver extends LoggableServiceObserverAdapter
{
    const CACHE_IGNORE = 'IGNORE';
    const CACHE_HIT = 'HIT';
    const CACHE_MISS = 'MISS';

    /**
     * @var RedisStorageFactory
     */
    protected $redisStorageFactory;

    /**
     * Contains a stack of cached responses
     *
     * @var array
     */
    protected $callStack;

    /**
     * @var RequestMatcher[]
     */
    protected $requestMatchers;

    /**
     * Construct the cache, and use $cache as the cache container object.
     *
     * @param RedisStorageFactory $redisStorageFactory
     */
    public function __construct(RedisStorageFactory $redisStorageFactory)
    {
        $this->redisStorageFactory = $redisStorageFactory;
        $this->requestMatchers = [];
        $this->callStack = [];
    }

    /**
     * @param RequestMatcher $requestMatcher
     */
    public function attachRequestMatcher(RequestMatcher $requestMatcher)
    {
        $this->requestMatchers [] = $requestMatcher;
    }

    /**
     * Notifies the Cache of a service method that is about to be executed. If the Cache has a response in the cache
     * container, the request is cancelled and the response is overwritten with the cached response.
     *
     * @param \Zicht\Service\Common\ServiceCallInterface $event
     * @return void
     */
    public function notifyBefore(ServiceCallInterface $event)
    {
        $request = $event->getRequest();
        $requestMatcher = $this->getRequestMatcher($request);

        // Early return when this request does not have a matcher
        if (null === $requestMatcher) {
            $this->callStack[] = [self::CACHE_IGNORE, null, null];
            return;
        }

        $key = $requestMatcher->getKey($request);
        $redis = $this->redisStorageFactory->getClient();
        $value = $redis->get($key);
        if (false === $value) {
            //
            // Cache miss
            //

            $this->callStack[] = [self::CACHE_MISS, $key, $requestMatcher->getTtl($request)];
            $this->addLogRecord(self::DEBUG, 'Cache miss', [$key]);
        } else {
            //
            // Cache hit
            //

            // Cancel the actual request
            $event->cancel($this);
            $event->getResponse()->setResponse($redis->get($key));
            $this->callStack[] = [self::CACHE_HIT, $key, null];
            $this->addLogRecord(self::DEBUG, 'Cache hit', [$key]);
        }
    }

    /**
     * If the notifyBefore() has staged a cached response, the response will be overwritten with the cached version.
     * If there is a response and no fault, and the response is cachable by this observer, it is stored in the cache
     * for future reuse.
     *
     * @param \Zicht\Service\Common\ServiceCallInterface $event
     * @return void
     */
    public function notifyAfter(ServiceCallInterface $event)
    {
        list($cacheEvent, $key, $ttl) = array_pop($this->callStack);

        switch ($cacheEvent) {
            case self::CACHE_IGNORE:
            case self::CACHE_HIT:
                break;

            case self::CACHE_MISS:
                $response = $event->getResponse();
                if (!$response->isError() && $response->isCachable()) {
                    $redis = $this->redisStorageFactory->getClient();
                    $redis->setex($key, $ttl, $response->getResponse());
                    $this->addLogRecord(self::DEBUG, 'Cache write', [$key, $ttl]);
                }
                break;
        }
    }

    /**
     * Given $request returns the first RequestMatcher that supports that request
     *
     * @param RequestInterface $request
     * @return null|RequestMatcher
     */
    protected function getRequestMatcher(RequestInterface $request)
    {
        foreach ($this->requestMatchers as $requestMatcher) {
            if ($requestMatcher->isMatch($request)) {
                return $requestMatcher;
            }
        }

        return null;
    }
}
