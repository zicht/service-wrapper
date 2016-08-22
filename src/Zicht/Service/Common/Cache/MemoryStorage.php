<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

/**
 * Storage "in-memory", i.e. the response is stored in a property of the cache object, which will (of course) be gone
 * the next page load.
 */
class MemoryStorage implements Storage
{
    /**
     * Construct the object storage with the specified container
     *
     * @param object $container
     */
    public function __construct($container)
    {
        $this->container = $container;
    }


    /**
     * Read a response from the cache container object.
     *
     * @param string $key
     * @return mixed
     */
    public function read($key)
    {
        return $this->container->{$key}['data'];
    }


    /**
     * Write a response to the cache.
     *
     * @param string $key
     * @param mixed $data
     * @param int $ttl
     * @return void
     */
    public function write($key, $data, $ttl)
    {
        $this->container->{$key} = array(
            'data' => $data,
            'time' => time(),
            'ttl' => $ttl
        );
    }


    /**
     * Checks if the cache container has the passed request cached.
     *
     * @param string $key
     * @param int $ttl
     * @return bool
     */
    public function isValid($key, $ttl)
    {
        $ret = false;
        if (isset($this->container->{$key})) {
            if (!is_array($this->container->$key)
            || !isset($this->container->{$key}['data'])
            || !isset($this->container->{$key}['time'])
            ) {
                // format is invalid, value was set from outside and should be purged
                $ret = false;
            } elseif (is_null($ttl) && empty($this->container->{$key}['ttl'])) {
                $ret = true;
            } else {
                if (!empty($this->container->{$key}['ttl'])) {
                    $ttl = min($this->container->{$key}['ttl'], $ttl);
                }
                $mtime = $this->container->{$key}['ttl'];
                $ret = $mtime + $ttl > time();
            }
            if (!$ret) {
                $this->invalidate($key);
            }
        }
        return $ret;
    }

    /**
     * Returns all keys in storage
     *
     * @return array|\Traversable
     */
    public function getKeys()
    {
        return array_keys(get_object_vars($this->container));
    }


    /**
     * Remove an item from memory.
     *
     * @param string $key
     * @return void
     */
    public function invalidate($key)
    {
        unset($this->container->{$key});
    }
}
