<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

use Zicht\Service\Common\RequestInterface;
use Zicht\Service\Common\ResponseInterface;

/**
 * The cache is a bridge between the Storage and the RequestMatcher.
 */
class CacheAdapter implements CacheConfiguration
{
    /**
     * Constructor.
     *
     * @param RequestMatcher $matcher
     * @param Storage $backend
     */
    public function __construct(RequestMatcher $matcher, Storage $backend)
    {
        $this->matcher = $matcher;
        $this->storage = $backend;
    }

    /**
     * Read the specified request from the cache and return the original Response
     *
     * @param RequestInterface $request
     * @return mixed
     */
    public function read(RequestInterface $request)
    {
        return $this->storage->read($this->matcher->getKey($request));
    }

    /**
     * Write the specified response to the cache
     *
     * @param \Zicht\Service\Common\RequestInterface $request
     * @param \Zicht\Service\Common\ResponseInterface $response
     * @return void
     */
    public function write(RequestInterface $request, ResponseInterface $response)
    {
        $this->storage->write(
            $this->matcher->getKey($request),
            $response->getResponse(),
            $this->matcher->getTtl($request)
        );
    }

    /**
     * @{inheritDoc}
     */
    public function isCachable(RequestInterface $request)
    {
        $isCachable = $this->matcher->isMatch($request);

        if ($expunger = $this->matcher->isExpunger($request)) {
            $this->expunge($expunger);
        }

        return $isCachable;
    }

    /**
     * @{inheritDoc}
     */
    public function getCacheKey(RequestInterface $request)
    {
        return $this->matcher->getKey($request);
    }

    /**
     * Check if this cache has the specified request cached
     *
     * @param \Zicht\Service\Common\RequestInterface $request
     * @return bool
     */
    public function isValid(RequestInterface $request)
    {
        return $this->storage->isValid($this->matcher->getKey($request), $this->matcher->getTtl($request));
    }


    /**
     * {@inheritDoc}
     */
    public function expunge($callback = true)
    {
        $ret = 0;
        foreach ($this->storage->getKeys() as $key) {
            if ($callback === true || (is_callable($callback) && $callback($key) === true)) {
                $this->storage->invalidate($key);
                $ret ++;
            }
        }
        return $ret;
    }

    public function claimExclusiveAccess(RequestInterface $request)
    {
        return $this->storage->claimExclusiveAccess($this->matcher->getKey($request));
    }

    public function releaseExclusiveAccess(RequestInterface $request)
    {
        return $this->storage->releaseExclusiveAccess($this->matcher->getKey($request));
    }

    public function subscribe(RequestInterface $request)
    {
        return $this->storage->subscribe($this->matcher->getKey($request));
    }

    public function publish(RequestInterface $request, ResponseInterface $response)
    {
        return $this->storage->publish($this->matcher->getKey($request), $response->getResponse());
    }

    public function transactionBlock(callable $callback)
    {
        return $this->storage->transactionBlock($callback);
    }

    /**
     * Cache configuration needs to be able to explain itself for logging purposes
     *
     * @return string
     */
    public function __toString()
    {
        return sprintf('matcher: [%s], storage: [%s]', get_class($this->matcher), get_class($this->storage));
    }
}
