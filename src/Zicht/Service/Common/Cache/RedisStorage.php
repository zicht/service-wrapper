<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

/**
 * Storage implementation for storage in Redis
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
        return (bool)$this->redis->exists($key);
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

    public function claimExclusiveAccess($key)
    {
        $this->init();
        dump(['incr', sprintf('exclusive-access::%s', $key)]);
        return 1 === $this->redis->incr(sprintf('exclusive-access::%s', $key));
    }

    public function releaseExclusiveAccess($key)
    {
        $this->init();
        dump(['decr', sprintf('exclusive-access::%s', $key)]);
        return $this->redis->decr(sprintf('exclusive-access::%s', $key));
    }

    public function subscribe($key)
    {
        dump(['subscribe', 'exclusive-access-notification-channel', $key]);

        // Create a new Redis connection to use `->subscribe`.  This
        // is required because the redis client that we use does not provide
        // any way of un-subscribing, the only way we can do that is by
        // disconnecting from Redis.
        $redis = $this->createRedisClient();
        $redis->subscribe(['exclusive-access-notification-channel'], function (\Redis $redis, $channel, $message) use ($key) {
            if ($message === (string)$key) {
                // wake-up
                $redis->close();
            }
        });

        return $this->read($key);
    }

    public function publish($key, $data)
    {
        $this->init();
        dump(['publish', 'exclusive-access-notification-channel', $key]);
        $this->redis->publish('exclusive-access-notification-channel', $key);
    }

    public function transactionBlock(callable $callback)
    {
        $this->init();
        dump(['multi']);
        $hasException = false;
        $this->redis->multi();
        try {
            $callback();
        } catch (\Exception $exception) {
            $hasException = true;
            dump(['discard']);
            $this->redis->discard();

            throw $exception;
        } finally {
            if (!$hasException) {
                dump(['exec']);
                $this->redis->exec();
            }
        }
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
