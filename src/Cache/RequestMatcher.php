<?php declare(strict_types=1);
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

use Zicht\Service\Common\RequestInterface;

/**
 * The request matcher can tell which requests a specific storage can cache, what key to use (i.e., what identifies
 * the specified request) and what the TTL for a request is.
 */
interface RequestMatcher
{
    /**
     * Return if the current request matcher is a candidate for the specified request
     *
     * @return bool
     */
    public function isMatch(RequestInterface $request);

    /**
     * Return true if the request might cause some caches to invalidate for the current matcher.
     *
     * For example: Updating a city name would cause the City entity's cache to invalidate.
     *
     * @return bool|callable
     */
    public function isExpunger(RequestInterface $request);

    /**
     * Return the storage key for the specified request
     *
     * @return string
     */
    public function getKey(RequestInterface $request);

    /**
     * Return an [value, error, grace] array containing the TTL values
     *
     * Where:
     * - value is the base TTL for storing a value
     * - error is the base TTL for storing an exception
     * - grace is the additional grace TTL for both $value and $error to remain usable
     *
     * @return int[]
     */
    public function getTtlConfig(RequestInterface $request);
}
