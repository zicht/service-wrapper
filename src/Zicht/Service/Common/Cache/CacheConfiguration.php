<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

use Zicht\Service\Common\RequestInterface;
use Zicht\Service\Common\ResponseInterface;

/**
 * Interface for a cache configuration
 */
interface CacheConfiguration
{
    /**
     * Read the specified request from the cache and return the original Response
     *
     * @param RequestInterface $request
     * @return RequestInterface
     */
    public function read(RequestInterface $request);

    /**
     * Write the specified response to the cache
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return void
     */
    public function write(RequestInterface $request, ResponseInterface $response);

    /**
     * Check if this cache is able to cache the specified request
     *
     * @param RequestInterface $request
     * @return bool
     */
    public function isCachable(RequestInterface $request);


    /**
     * Check if this cache has the specified request cached
     *
     * @param RequestInterface $request
     * @return bool
     */
    public function isValid(RequestInterface $request);


    /**
     * Expunge all keys for which the callback returns boolean true
     *
     * @param bool|callable $callback
     * @return int The number of expunged objects
     */
    public function expunge($callback = true);


    /**
     * Cache configuration needs to be able to explain itself for logging purposes
     *
     * @return string
     */
    public function __toString();
}
