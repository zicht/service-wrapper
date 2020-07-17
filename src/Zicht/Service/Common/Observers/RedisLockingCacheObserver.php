<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use Zicht\Service\Common\ServiceCallInterface;

class RedisLockingCacheObserver extends RedisCacheObserver
{
    /** @var int */
    const LOCK_ATTEMPT_WARNING_THRESHOLD = 10;

    /** @var string */
    const UNLOCK_SCRIPT = '
    if redis.call("GET", KEYS[1]) == ARGV[1] then
        return redis.call("DEL", KEYS[1])
    else
        return 0
    end
';

    /** @var int */
    protected $minLockTTLSeconds = 3;

    /** @var int */
    protected $minLockSleepMicroSeconds = 100;

    /** @var int */
    protected $maxLockSleepMicroSeconds = 200;

    /**
     * @param integer $minLockTTL in seconds
     * @param integer $minLockSleepSeconds in seconds
     * @param integer $maxLockSleepSeconds in seconds
     */
    public function configure($minLockTTL, $minLockSleepSeconds, $maxLockSleepSeconds)
    {
        $this->minLockTTLSeconds = $minLockTTL;
        $this->minLockSleepMicroSeconds = $minLockSleepSeconds * 1000;
        $this->maxLockSleepMicroSeconds = $maxLockSleepSeconds * 1000;
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
            $this->callStack[] = ['type' => self::CACHE_IGNORE];
            return;
        }

        $key = $requestMatcher->getKey($request);
        $redis = $this->redisStorageFactory->getClient();
        $value = $redis->get($key);
        if (false === $value) {
            //
            // Cache miss
            //

            // Claim exclusive write access
            $ttlSeconds = $requestMatcher->getTtl($request);
            $token = $this->createToken();
            $lockKey = sprintf('LOCK::%s', $key);

            // Obtain a lock.  The lock will timeout after 5 seconds by Redis when we fail to clear it
            $this->lock($redis, $lockKey, $token, min($this->minLockTTLSeconds, $ttlSeconds));

            // Check if the value has already been set by another process
            $value = $redis->get($key);
            if (false === $value) {
                // Redis did not receive the data while we were waiting to obtain the lock, therefore,
                // we will let the call though to the service
                $this->callStack[] = ['type' => self::CACHE_MISS, 'key' => $key, 'ttlSeconds' => $ttlSeconds, 'lockKey' => $lockKey, 'token' => $token];
                $this->addLogRecord(self::DEBUG, 'CacheObserver miss', [$key]);
                return;
            } else {
                // Redis received the data while we were waiting to obtain the lock, therefore,
                // we will use the data that is already there
                $this->unlock($redis, $lockKey, $token);
            }
        }

        //
        // Cache hit
        //

        // Cancel the actual request
        $event->cancel($this);
        $event->getResponse()->setResponse($value);
        $this->callStack[] = ['type' => self::CACHE_HIT];
        $this->addLogRecord(self::DEBUG, 'CacheObserver hit', [$key]);
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
        $item = array_pop($this->callStack);

        switch ($item['type']) {
            case self::CACHE_IGNORE:
            case self::CACHE_HIT:
                break;

            case self::CACHE_MISS:
                $response = $event->getResponse();
                $redis = $this->redisStorageFactory->getClient();
                try {
                    if (!$response->isError() && $response->isCachable()) {
                        $redis->setex($item['key'], $item['ttlSeconds'], $response->getResponse());
                        $this->addLogRecord(self::DEBUG, 'CacheObserver write', $item);
                    }
                } finally {
                    $this->unlock($redis, $item['lockKey'], $item['token']);
                }
                break;
        }
    }

    /**
     * Returns a new and unique token string
     *
     * @return string
     */
    protected function createToken()
    {
        return uniqid('token-');
    }

    /**
     * Obtain an exclusive lock using the RedLock algorithm
     *
     * https://github.com/ronnylt/redlock-php/blob/master/src/RedLock.php
     *
     * @param \Redis $redis
     * @param string $lockKey
     * @param string $token
     * @param integer $ttlSeconds
     */
    protected function lock(\Redis $redis, $lockKey, $token, $ttlSeconds)
    {
        $attemptCounter = 1;
        while (!($res = $redis->set($lockKey, $token, ['nx', 'ex' => $ttlSeconds]))) {
            usleep(mt_rand($this->minLockSleepMicroSeconds, $this->maxLockSleepMicroSeconds));
            $attemptCounter++;
        }

        if ($attemptCounter > self::LOCK_ATTEMPT_WARNING_THRESHOLD) {
            $this->addLogRecord(self::WARNING, 'CacheObserver locked', ['lockKey' => $lockKey, 'attemptCounter' => $attemptCounter, 'ttlSeconds' => $ttlSeconds, 'minLockSleepMicroSeconds' => $this->minLockSleepMicroSeconds, 'maxLockSleepMicroSeconds' => $this->maxLockSleepMicroSeconds]);
        }
    }

    /**
     * Release an exclusive lock using the RedLock algorithm
     *
     * https://github.com/ronnylt/redlock-php/blob/master/src/RedLock.php
     *
     * @param \Redis $redis
     * @param string $lockKey
     * @param string $token
     */
    protected function unlock(\Redis $redis, $lockKey, $token)
    {
        // Note: we serialize the $token because it is still serialized when using eval
        if (!$redis->eval(self::UNLOCK_SCRIPT, [$lockKey, $redis->_serialize($token)], 1)) {
            $this->addLogRecord(self::WARNING, 'CacheObserver unlock fail', [$lockKey, $token]);
        }
    }
}
