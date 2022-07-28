<?php declare(strict_types=1);
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use Psr\Log\LogLevel;
use Zicht\Service\Common\ServiceCallInterface;

/**
 * A service calls' result that allows caching will be stored and retrieved in Redis
 *
 * The value stored in redis will have the form:
 * [
 *   'g' => 15,     // Grace TTL
 *   'e' => null,   // Exception, or null when value is present
 *   'v' => null,   // Value, or null when exception is present
 * ]
 */
class RedisLockingCacheObserver extends RedisCacheObserver
{
    /** @var string */
    public const CACHE_IGNORE = 'IGNORE';

    /** @var string */
    public const CACHE_HIT = 'HIT';

    /** @var string */
    public const CACHE_MISS = 'MISS';

    /** @var int */
    public const LOCK_FAILURE_WARNING_THRESHOLD = 3;

    /** @var string */
    public const UNLOCK_SCRIPT = '
    if redis.call("GET", KEYS[1]) == ARGV[1] then
        return redis.call("DEL", KEYS[1])
    else
        return 0
    end
';

    /** @var array Contains a stack of cached responses */
    protected $callStack = [];

    /** @var int */
    protected $minLockTTLSeconds = 3;

    /** @var int */
    protected $minLockSleepMicroSeconds = 100000;

    /** @var int */
    protected $maxLockSleepMicroSeconds = 200000;

//    /** @var int[] */
//    protected $statistics = [
//        'grace-ignore' => 0,        // The TTL is stil valid, no need to refresh cache (after serving http response)
//        'grace-invalid' => 0,       // Determined that cache refresh is needed (after serving http response)
//        'grace-late-ignore' => 0,   // Attempted to refresh the cache but another process already did so.  Slight Redis and CPU overhead (after serving http response)
//        'grace-refresh' => 0,       // Calling service to refresh the cache (after serving http response)
//        'grace-valid' => 0,         // Determined that cache refresh is *not* needed (after serving http response)
//        'hit' => 0,                 // The TTL and/or grace is still valid, data served from cache, no service call is made
//        'lock-failure' => 0,        // Attempt to lock a key beforce calling service has failed, *no* service call is made
//        'lock-success' => 0,        // Attempt to lock a key beforce calling service has succeeded, service call is made
//        'miss' => 0,                // Calling service to refresh the cache
//        'unlock-failure' => 0,      // Attempt to unlock a key has failed
//        'unlock-success' => 0,      // Attempt to unlock a key has succeeded
//    ];

    /**
     * @param int $minLockTTL in seconds
     * @param int $minLockSleepSeconds in seconds
     * @param int $maxLockSleepSeconds in seconds
     */
    public function configure($minLockTTL, $minLockSleepSeconds, $maxLockSleepSeconds)
    {
        $this->minLockTTLSeconds = $minLockTTL;
        $this->minLockSleepMicroSeconds = $minLockSleepSeconds * 1000000;
        $this->maxLockSleepMicroSeconds = $maxLockSleepSeconds * 1000000;
    }

    /**
     * Notifies the Cache of a service method that is about to be executed. If the Cache has a response in the cache
     * container, the request is cancelled and the response is overwritten with the cached response.
     *
     * @return void
     */
    public function notifyBefore(ServiceCallInterface $call)
    {
        // todo: should not do anything when cancelled... but the callStack should not corrupt
//        if ($call->isCancelled()) {
//            return;
//        }

        $request = $call->getRequest();
        $requestMatcher = $this->getRequestMatcher($request);
        $isTerminating = $call->isTerminating();

        // Early return when this request does not have a matcher
        if (null === $requestMatcher) {
            return;
        }

        $key = $requestMatcher->getKey($request);
        $ttlConfig = $requestMatcher->getTtlConfig($request);
        $redis = $this->redisStorageFactory->getClient();

        if ($isTerminating) {
            // Claim exclusive write access
            $token = $this->createToken();
            $lockKey = sprintf('LOCK::%s', $key);

            // Obtain a lock.  The lock will timeout after 5 seconds by Redis when we fail to clear it
            if ($this->lock($redis, $lockKey, $token, min($this->minLockTTLSeconds, $ttlConfig['value']), false)) {
                // Check if the value has already been set by another process
                $ttlRemaining = $redis->ttl($key);
                if ($ttlRemaining === false || $ttlRemaining < $ttlConfig['grace']) {
                    // Redis did not receive the data while we were waiting to obtain the lock, therefore,
                    // we will let the call though to the service
                    $this->callStack[] = ['type' => self::CACHE_MISS, 'key' => $key, 'ttlConfig' => $ttlConfig, 'lockKey' => $lockKey, 'token' => $token];
                    $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'grace-refresh', 'key' => $key]);
                    return;
                } else {
                    // Redis received the data while we were waiting to obtain the lock, therefore,
                    // we will use the data that is already there
                    $this->unlock($redis, $lockKey, $token);
                    $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'grace-late-ignore', 'key' => $key]);
                }
            } else {
                $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'grace-ignore', 'key' => $key]);
            }

            // We did not obtain the lock or redis received the data while we were waiting to obtain
            // the lock, therefore, we do not want to continue with this request, since another process
            // is already doing so
            // Note that we can not simply `$call->cancel($this)` because that should still run
            // other observers, which expect data to exist, which we haven't.  Therefore,
            // we will throw an exception instead
            throw new RedisCacheTerminateException('Terminate service call because other process is doing or did the work');
        } else {
            $value = $redis->get($key);
            if (false === $value) {
                //
                // Cache miss
                //

                // Claim exclusive write access
                $token = $this->createToken();
                $lockKey = sprintf('LOCK::%s', $key);

                // Obtain a lock.  The lock will timeout after 5 seconds by Redis when we fail to clear it
                $this->lock($redis, $lockKey, $token, min($this->minLockTTLSeconds, $ttlConfig['value'] + $ttlConfig['grace']), true);

                // Check if the value has already been set by another process
                $value = $redis->get($key);
                if (false === $value) {
                    // Redis did not receive the data while we were waiting to obtain the lock, therefore,
                    // we will let the call though to the service
                    $this->callStack[] = ['type' => self::CACHE_MISS, 'key' => $key, 'ttlConfig' => $ttlConfig, 'lockKey' => $lockKey, 'token' => $token];
                    $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'miss', 'key' => $key]);
                    return;
                } else {
                    // Redis received the data while we were waiting to obtain the lock, therefore,
                    // we will use the data that is already there
                    $this->unlock($redis, $lockKey, $token);
                }
            } else {
                //
                // Cache hit
                //

                if ($ttlConfig['grace'] > 0) {
                    // Ensure that, on terminate, we check if the cache is in its grace period
                    $this->graceChecks[$key] = ['ttlConfig' => $ttlConfig, 'method' => $request->getMethod(), 'parameters' => $request->getParameters(), 'service' => $call->getService()];
                }
            }
        }

        //
        // Cache hit (possibly after busy-wait)
        //

        // Cancel the actual request
        $call->cancel($this);
        // Provide ether the response or the error from the cache
        if ($value['e'] === null) {
            $call->getResponse()->setResponse($value['v']);
        } else {
            $call->getResponse()->setError($value['e']);
        }
        $this->callStack[] = ['type' => self::CACHE_HIT];
        $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'hit', 'key' => $key]);
    }

    /**
     * If the notifyBefore() has staged a cached response, the response will be overwritten with the cached version.
     * If there is a response and no fault, and the response is cachable by this observer, it is stored in the cache
     * for future reuse.
     *
     * @return void
     */
    public function notifyAfter(ServiceCallInterface $call)
    {
        $request = $call->getRequest();
        if (null === ($requestMatcher = $this->getRequestMatcher($request))) {
            return;
        }
        $key = $requestMatcher->getKey($request);

        // It is possible that one or more entries should be skipped when an exception is thrown,
        // possibly in another observer, between running $this->notifyBefore and $this->notifyAfter.
        while ($item = array_pop($this->callStack)) {
            switch ($item['type']) {
                case self::CACHE_IGNORE:
                case self::CACHE_HIT:
                    // We will assume no stack-corruption has occurred, and just pop the one item
                    return;

                case self::CACHE_MISS:
                    $redis = $this->redisStorageFactory->getClient();
                    try {
                        if ($item['key'] === $key) {
                            $response = $call->getResponse();
                            if ($response->isCachable()) {
                                $ttlConfig = $item['ttlConfig'];
                                if (($error = $response->getError()) === null) {
                                    $redis->setex(
                                        $item['key'],
                                        $ttlConfig['value'] + $ttlConfig['grace'],
                                        ['g' => $ttlConfig['grace'], 'e' => null, 'v' => $response->getResponse()]
                                    );
                                    $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'write', 'key' => $item['key']]);
                                } elseif ($ttlConfig['error'] > 0) {
                                    $this->makeExceptionSerializable($error);
                                    $redis->setex(
                                        $item['key'],
                                        $ttlConfig['error'] + $ttlConfig['grace'],
                                        ['g' => $ttlConfig['grace'], 'e' => $error, 'v' => null]
                                    );
                                    $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'write-error', 'key' => $item['key']]);
                                }
                            }

                            // todo: add unit test to test that a previously crashed request is unlocked and also the actual request
                            return;
                        } else {
                            $this->logger->log(LogLevel::WARNING, 'Cache', ['type' => 'stack-corruption', 'key' => $key, 'faulty-key' => $item['key']]);
                            // Unlock this key and try the next item in $this->callStack
                        }
                    } finally {
                        $this->unlock($redis, $item['lockKey'], $item['token']);
                    }
                    break;
            }
        }
    }

    /**
     * Unlock any calls that are left and check grace-perdiod for eligible calls
     *
     * @return void
     */
    public function terminate()
    {
        // Very rarely a lock may remain, free them to prevent processes blocking untilthe lock timeout occurs
        while ($item = array_pop($this->callStack)) {
            switch ($item['type']) {
                case self::CACHE_IGNORE:
                case self::CACHE_HIT:
                    break;

                case self::CACHE_MISS:
                    $this->logger->log(LogLevel::WARNING, 'Cache', ['type' => 'late-stack-corruption', 'faulty-key' => $item['key']]);
                    $redis = $this->redisStorageFactory->getClient();
                    $this->unlock($redis, $item['lockKey'], $item['token']);
                    break;
            }
        }

        parent::terminate();
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
     * @param string $lockKey
     * @param string $token
     * @param int $ttlSeconds
     * @param bool $wait
     * @return bool
     */
    protected function lock(\Redis $redis, $lockKey, $token, $ttlSeconds, $wait)
    {
        if ($wait) {
            $failureCounter = 0;
            while (!$redis->set($lockKey, $token, ['nx', 'ex' => $ttlSeconds])) {
                usleep(mt_rand($this->minLockSleepMicroSeconds, $this->maxLockSleepMicroSeconds));
                $this->logger->log(
                    ++$failureCounter < self::LOCK_FAILURE_WARNING_THRESHOLD ? LogLevel::DEBUG : LogLevel::WARNING,
                    'Cache',
                    ['type' => 'lock-failure', 'key' => $lockKey, 'wait' => (int)$wait, 'failureCounter' => $failureCounter, 'ttlSeconds' => $ttlSeconds, 'minLockSleepMicroSeconds' => $this->minLockSleepMicroSeconds, 'maxLockSleepMicroSeconds' => $this->maxLockSleepMicroSeconds]
                );
            }
            $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'lock-success', 'key' => $lockKey, 'wait' => (int)$wait]);
            return true;
        } else {
            if ($redis->set($lockKey, $token, ['nx', 'ex' => $ttlSeconds])) {
                $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'lock-success', 'key' => $lockKey, 'wait' => (int)$wait]);
                return true;
            } else {
                $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'lock-failure', 'key' => $lockKey, 'wait' => (int)$wait]);
                return false;
            }
        }
    }

    /**
     * Release an exclusive lock using the RedLock algorithm
     *
     * https://github.com/ronnylt/redlock-php/blob/master/src/RedLock.php
     *
     * @param string $lockKey
     * @param string $token
     */
    protected function unlock(\Redis $redis, $lockKey, $token)
    {
        // Note: we serialize the $token because it is still serialized when using eval
        if ($redis->eval(self::UNLOCK_SCRIPT, [$lockKey, $redis->_serialize($token)], 1)) {
            $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'unlock-success', 'key' => $lockKey]);
        } else {
            $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'unlock-fail', 'key' => $lockKey, 'token' => $token]);
        }
    }
}
