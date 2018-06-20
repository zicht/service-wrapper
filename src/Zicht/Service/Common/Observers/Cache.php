<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use Zicht\Service\Common\Cache\CacheConfiguration;
use Zicht\Service\Common\ServiceCallInterface;

/**
 * The Cache observer caches responses per identified method in the passed cache container as object properties.
 *
 * This could be used to cache responses per request (and using an in-memory storage as a cache container) or for
 * per-user caches that store information in the session, or for application wide responses, in another (application-
 * wide cache).
 *
 * The actual storage of the cache contents is not up to the observer, that is up to the component that initializes
 * the Cache.
 */
class Cache extends LoggableServiceObserverAdapter
{
    const CACHE_HIT = 'HIT';
    const CACHE_ACTIVE_MISS = 'ACTIVE_MISS';
    const CACHE_PASSIVE_MISS = 'PASSIVE_MISS';

    /**
     * Contains a stack of cached responses
     *
     * @var array
     */
    protected $stack;


    /**
     * @var CacheConfiguration
     */
    protected $container;

    /**
     * Construct the cache, and use $cache as the cache container object.
     *
     * @param CacheConfiguration $cache
     */
    public function __construct(CacheConfiguration $cache)
    {
        $this->stack = [];
        $this->container = $cache;
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
        if ($this->container->isCachable($event->getRequest())) {
            $this->addLogRecord(self::DEBUG, '-----------------------------------');

            if ($this->container->isValid($event->getRequest())) {
                // Cancel the actual request
                $event->cancel($this);
                $event->getResponse()->setResponse($this->container->read($event->getRequest()));
                $this->stack[] = self::CACHE_HIT;
                $this->addLogRecord(self::DEBUG, sprintf('Cache hit: %s', $this->container->getCacheKey($event->getRequest())));
            } else {
                if ($this->container->claimExclusiveAccess($event->getRequest())) {
                    // We were able to claim exclusive access, therefore, we will continue the service call
                    // and the response will be written when it returns
                    $this->stack[] = self::CACHE_ACTIVE_MISS;
                    $this->addLogRecord(self::DEBUG, sprintf('Cache active-miss: %s', $this->container->getCacheKey($event->getRequest())));
                } else {
                    // We were *not* able to claim exclusive access, therefore, we will wait until
                    // the process that did claim access responds
                    $this->stack[] = self::CACHE_PASSIVE_MISS;
                    $this->addLogRecord(self::DEBUG, sprintf('Cache passive-miss: %s', $this->container->getCacheKey($event->getRequest())));

                    // Cancel the actual request
                    $event->cancel($this);
                    $event->getResponse()->setResponse($this->container->subscribe($event->getRequest()));
                    $this->addLogRecord(self::DEBUG, sprintf('Cache passive-hit: %s', $this->container->getCacheKey($event->getRequest())));
                }
            }
        } else {
            $this->stack[] = false;
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
        switch (array_pop($this->stack)) {
            case self::CACHE_HIT:
                break;

            case self::CACHE_ACTIVE_MISS:
//                sleep(10);

                if (!$event->getResponse()->isError() && $event->getResponse()->isCachable()) {
                    $this->container->write($event->getRequest(), $event->getResponse());
                    $this->addLogRecord(self::DEBUG, sprintf('Cache store: %s', $this->container->getCacheKey($event->getRequest())));
                }

                $this->container->transactionBlock(function () use ($event) {
                    $this->container->releaseExclusiveAccess($event->getRequest());
                    $this->container->publish($event->getRequest(), $event->getResponse());
                });
                break;

            case self::CACHE_PASSIVE_MISS:
                $this->container->releaseExclusiveAccess($event->getRequest());
                break;
        }
    }
}
