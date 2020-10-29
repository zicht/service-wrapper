<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Zicht\Service\Common\Cache\RequestMatcher;
use Zicht\Service\Common\RequestInterface;
use Zicht\Service\Common\ServiceCallInterface;
use Zicht\Service\Common\Storage\RedisStorageFactory;

/**
 * A service calls' result that allows caching will be stored and retrieved in Redis
 *
 * The value stored in redis will have the form:
 * [
 *   'g' => 15,     // Grace TTL
 *   'e' => null,   // Exception, or null when value is present
 *   'v' => null,   // Value, or null when exception is present
 * ]
 *
 * @todo Implement grace caching (currently only the RedisLockingCacheObserver supports grace-cache)
 */
class RedisCacheObserver extends ServiceObserverAdapter implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var RedisStorageFactory */
    protected $redisStorageFactory;

    /** @var RequestMatcher[] */
    protected $requestMatchers = [];

    /** @var array Contains a mapping with caches that need to have their cache TTL checked */
    protected $graceChecks = [];

    public function __construct(RedisStorageFactory $redisStorageFactory)
    {
        $this->logger = new NullLogger();
        $this->redisStorageFactory = $redisStorageFactory;
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
     * @param ServiceCallInterface $call
     * @return void
     */
    public function notifyBefore(ServiceCallInterface $call)
    {
        if ($call->isCancelled()) {
            return;
        }

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
            // Check if the value has already been set by another process
            $ttlRemaining = $redis->ttl($key);
            if ($ttlRemaining === false || $ttlRemaining < $ttlConfig['grace']) {
                // Redis does not have the data, therefore, we will let the call though to the service
                $call->setInfo('RedisCacheObserver--Miss', ['key' => $key, 'ttlConfig' => $ttlConfig]);
                $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'grace-refresh', 'key' => $key]);
                return;
            } else {
                // Redis already has the data, therefore, we do not need to do anything anymore
                $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'grace-late-ignore', 'key' => $key]);

                // Note that we can not simply `$call->cancel($this)` because that whould still run
                // other observers, which expect data to exist, which we haven't.  Therefore,
                // we will throw an exception instead
                throw new RedisCacheTerminateException('Terminate service call because other process is doing or did the work');
            }
        } else {
            $value = $redis->get($key);
            if (false === $value) {
                //
                // Cache miss
                //

                $call->setInfo('RedisCacheObserver--Miss', ['key' => $key, 'ttlConfig' => $ttlConfig]);
                $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'miss', 'key' => $key]);
            } else {
                //
                // Cache hit
                //

                // Cancel the actual request
                $call->cancel($this);
                // Provide ether the response or the error from the cache
                if ($value['e'] === null) {
                    $call->getResponse()->setResponse($value['v']);
                } else {
                    $call->getResponse()->setError($value['e']);
                }
                $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'hit', 'key' => $key]);

                // Check if grace-period applies for this call
                if ($ttlConfig['grace'] > 0) {
                    // Ensure that, on terminate, we check if the cache is in its grace period
                    $this->graceChecks[$key] = ['ttlConfig' => $ttlConfig, 'method' => $request->getMethod(), 'parameters' => $request->getParameters(), 'service' => $call->getService()];
                }
            }
        }
    }

    /**
     * If the notifyBefore() has staged a cached response, the response will be overwritten with the cached version.
     * If there is a response and no fault, and the response is cachable by this observer, it is stored in the cache
     * for future reuse.
     *
     * @param \Zicht\Service\Common\ServiceCallInterface $call
     * @return void
     */
    public function notifyAfter(ServiceCallInterface $call)
    {
        if (($info = $call->getInfo('RedisCacheObserver--Miss')) === null) {
            return;
        }

        $response = $call->getResponse();
        if ($response->isCachable()) {
            $ttlConfig = $info['ttlConfig'];
            $redis = $this->redisStorageFactory->getClient();
            if (($error = $response->getError()) === null) {
                $redis->setex($info['key'], $ttlConfig['value'] + $ttlConfig['grace'], ['g' => $ttlConfig['grace'], 'e' => null, 'v' => $response->getResponse()]);
            } else {
                $this->makeExceptionSerializable($error);
                $redis->setex($info['key'], $ttlConfig['error'] + $ttlConfig['grace'], ['g' => $ttlConfig['grace'], 'e' => $error, 'v' => null]);
            }
            $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'write', 'key' => $info['key']]);
        }
    }

    /**
     * Check grace-perdiod for eligible calls
     *
     * @return void
     */
    public function terminate()
    {
        foreach ($this->graceChecks as $key => $item) {
            $redis = $this->redisStorageFactory->getClient();
            $ttlRemaining = $redis->ttl($key);
            if ($ttlRemaining === false || $ttlRemaining < $item['ttlConfig']['grace']) {
                // Perform the request again in 'terminate' mode
                $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'grace-invalid', 'key' => $key]);
                try {
                    $item['service']->__call($item['method'], $item['parameters']);
                } catch (RedisCacheTerminateException $exception) {
                    // pass
                }
            } else {
                $this->logger->log(LogLevel::DEBUG, 'Cache', ['type' => 'cache-valid', 'key' => $key]);
            }
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

    /**
     * We need exceptions to be serializable to allow them to be persisted in the cache
     *
     * Therefore, we need to remove the protected trace, which contains a closure,
     * which is not serializable.  This trace needs to be removed from $exception
     * and all its parent exceptions.
     *
     * @param \Exception $exception
     */
    protected function makeExceptionSerializable(\Exception &$exception)
    {
        $traceProperty = (new \ReflectionClass(\Exception::class))->getProperty('trace');
        $traceProperty->setAccessible(true);
        $ex = $exception;
        do {
            $traceProperty->setValue($ex, []);
        } while ($ex = $ex->getPrevious());
        $traceProperty->setAccessible(false);
    }
}
