<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use Zicht\Service\Common\Cache\RequestMatcher;
use Zicht\Service\Common\RequestInterface;
use Zicht\Service\Common\ServiceCallInterface;

/**
 * Class RedisCacheObserver
 */
class RedisCacheObserver extends LoggableServiceObserverAdapter
{
    const CACHE_IGNORE = 'IGNORE';
    const CACHE_HIT = 'HIT';
    const CACHE_ACTIVE_MISS = 'ACTIVE_MISS';
    const CACHE_PASSIVE_MISS = 'PASSIVE_MISS';

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
     * @var string
     */
    protected $redisHost;

    /**
     * @var integer
     */
    protected $redisPort;

    /**
     * @var string
     */
    protected $redisPrefix;

    /**
     * @var null|\Redis
     */
    protected $redisClient;

    /**
     * Construct the cache, and use $cache as the cache container object.
     *
     * @param string $redisHost
     * @param integer $redisPort
     * @param string $redisPrefix
     */
    public function __construct($redisHost, $redisPort, $redisPrefix)
    {
        $this->redisHost = $redisHost;
        $this->redisPort = $redisPort;
        $this->redisPrefix = $redisPrefix;
        $this->redisClient = null;
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
        $redis = $this->getRedisClient();
        $value = $redis->get($key);
        if (false === $value) {
            //
            // Cache miss
            //

            if (1 === $redis->incr(sprintf('exclusive-access-counter::%s', $key))) {
                // We were able to claim exclusive access, therefore, we will continue the service call
                // and the response will be written when it returns
                $this->callStack[] = [self::CACHE_ACTIVE_MISS, $key, $requestMatcher->getTtl($request)];
                $this->addLogRecord(self::DEBUG, 'Cache active-miss', [$key]);
            } else {
                // We were *not* able to claim exclusive access, therefore, we will wait until
                // the process that did claim access responds
                $this->callStack[] = [self::CACHE_PASSIVE_MISS, $key, null];
                $this->addLogRecord(self::DEBUG, 'Cache passive-miss', [$key]);

                // Create a new Redis connection to use `->subscribe`.  This
                // is required because the redis client that we use does not provide
                // any way of un-subscribing, the only way we can do that is by
                // disconnecting from Redis.
                $channelRedis = $this->createRedisClient();
                $channelRedis->subscribe(['exclusive-access-notification-channel'], function (\Redis $redis, $channel, $message) use ($key) {
                    if ($message === (string)$key) {
                        // wake-up
                        $redis->close();
                    }
                });

                // Cancel the actual request
                $event->cancel($this);
                $event->getResponse()->setResponse($redis->get($key));
                $this->addLogRecord(self::DEBUG, 'Cache passive-hit', [$key]);
            }
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

            case self::CACHE_ACTIVE_MISS:
//                sleep(10);

                $response = $event->getResponse();
                $redis = $this->getRedisClient();

                try {
                    if (!$response->isError() && $response->isCachable()) {
                        $redis->setex($key, $ttl, $response->getResponse());
                        $this->addLogRecord(self::DEBUG, 'Cache write', [$key, $ttl]);
                    }
                } finally {
                    $redis->multi();
                    $redis->decr(sprintf('exclusive-access-counter::%s', $key));
                    $redis->publish('exclusive-access-notification-channel', $key);
                    $redis->exec();
                }
                break;

            case self::CACHE_PASSIVE_MISS:
                $redis = $this->getRedisClient();
                $redis->decr(sprintf('exclusive-access-counter::%s', $key));
                break;
        }
    }

    /**
     * @return \Redis
     */
    protected function getRedisClient()
    {
        if (null === $this->redisClient) {
            $this->redisClient = $this->createRedisClient();
        }
        return $this->redisClient;
    }

    /**
     * @return \Redis
     */
    protected function createRedisClient()
    {
        $redisClient = new \Redis();
        $redisClient->connect($this->redisHost, $this->redisPort);
        $redisClient->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
        $redisClient->setOption(\Redis::OPT_PREFIX, sprintf('%s::', $this->redisPrefix));
        return $redisClient;
    }

    /**
     * @param $request
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
