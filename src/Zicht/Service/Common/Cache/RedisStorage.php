<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

/**
 * Storage implementation for file (disk) storage
 */
class RedisStorage extends RedisBase implements Storage
{
    /**
     * Constructor, overridden to make it public.
     *
     * @param string $host
     * @param string $prefix
     */
    public function __construct($host, $prefix)
    {
        parent::__construct($host, $prefix);
    }

    /**
     * @{inheritDoc}
     */
    public function read($key)
    {
        $this->init();
        return $this->redis->get($key);
    }


    /**
     * @{inheritDoc}
     */
    public function write($key, $data, $ttl)
    {
        $this->init();
        $this->redis->setex($key, $ttl, $data);
    }


    /**
     * Checks if the filesystem has the passed request cached. The TTL provided is matched against the file's mtime.
     *
     * @param string $key
     * @param int $ttl
     * @return bool
     */
    public function isValid($key, $ttl)
    {
        $this->init();
        return $this->redis->exists($key);
    }

    /**
     * Purges the specified object from cache
     *
     * @param string $key
     * @return void
     */
    public function invalidate($key)
    {
        $this->init();
        $this->redis->del($key);
    }


    /**
     * Returns all keys in storage
     *
     * @return array|\Traversable
     */
    public function getKeys()
    {
        $this->init();

        return array_map(
            function ($key) {
                return substr($key, strlen($this->prefix));
            },

            $this->redis->keys('*')
        );
    }
}
