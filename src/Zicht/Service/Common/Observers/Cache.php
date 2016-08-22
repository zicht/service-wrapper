<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
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
    /**
     * Contains a stack of cached responses
     *
     * @var array
     */
    protected $stack = array();


    /**
     * @var object|\Zicht\Service\Common\Cache\CacheAdapter
     */
    protected $container = null;

    /**
     * Construct the cache, and use $cache as the cache container object.
     *
     * @param \Zicht\Service\Common\Cache\CacheConfiguration $cache
     */
    public function __construct(CacheConfiguration $cache)
    {
        $this->container = $cache;
    }


    /**
     * Notifies the Cache of a service method that is about to be executed. If the Cache has a response in the cache
     * container, the request is cancelled and the response is overwritten with the cached response.
     *
     * @param \Zicht\Service\Common\ServiceCallInterface $event
     * @return bool
     */
    public function notifyBefore(ServiceCallInterface $event)
    {
        if ($this->container->isCachable($event->getRequest())) {
            if ($this->container->isValid($event->getRequest())) {
                // stack the cached response for return
                $response = $this->container->read($event->getRequest());
                // cancel the actual request
                $event->cancel($this);
                $event->getResponse()->setResponse($response);
                $this->addLogRecord(
                    self::DEBUG,
                    'Cache hit: ' . $event->getRequest()->getMethod(),
                    array(
                        'container' => $this->container,
                        'request' => $event->getRequest(),
                        'response' => $event->getResponse()
                    )
                );
                $this->stack[] = true;
            } else {
                $this->addLogRecord(
                    self::DEBUG,
                    'Cache miss: ' . $event->getRequest()->getMethod(),
                    array(
                        'container' => $this->container,
                        'request' => $event->getRequest()
                    )
                );
                $this->stack[]= false;
            }
        } else {
            $this->stack[]= false;
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
        if (!array_pop($this->stack)) {
            if (
                !$event->getResponse()->isError()
                && $this->container->isCachable($event->getRequest())
                && $event->getResponse()->isCachable()
            ) {
                $this->addLogRecord(
                    self::DEBUG,
                    'Cache store: ' . $event->getRequest()->getMethod(),
                    array('response' => $event->getResponse())
                );
                $this->container->write($event->getRequest(), $event->getResponse());
            }
        }
    }
}
