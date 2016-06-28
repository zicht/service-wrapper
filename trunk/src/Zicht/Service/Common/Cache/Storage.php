<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */
 
namespace Zicht\Service\Common\Cache;

/**
 * Common interface for cache storage
 */
interface Storage
{
    /**
     * Read the specified request from the cache and return the original Response
     *
     * @param string $key
     * @return mixed
     */
    public function read($key);

    /**
     * Write the specified response to the cache. The TTL may be specified if the cache keeps TTL's with the objects
     * (such as APC)
     *
     * @param string $key
     * @param mixed $data
     * @param int $ttl
     * @return void
     */
    public function write($key, $data, $ttl);

    /**
     * Checks if the specified key is valid in the cache. The TTL may be specified if the cache has an mtime for objects
     * (such as the filesystem)
     *
     * @param string $key
     * @param int $ttl
     * @return bool
     */
    public function isValid($key, $ttl);

    /**
     * Purges the specified object from cache
     *
     * @param string $key
     * @return void
     */
    public function invalidate($key);


    /**
     * Returns all keys in storage
     *
     * @return array|\Traversable
     */
    public function getKeys();
}