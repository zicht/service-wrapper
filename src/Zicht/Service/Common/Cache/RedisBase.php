<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

/**
 * Base class for redis-based storage
 */
class RedisBase
{
    /** @var string */
    protected $host;

    /** @var integer */
    protected $port;

    /** @var string */
    protected $prefix;

    /** @var boolean */
    protected $hasBeenInitialized;

    /** @var \Redis */
    protected $redis;

    /**
     * Constructor.
     *
     * The $host can be given as either a hostname, or a hostname:port combination
     *
     * @param string $host
     * @param string $prefix
     */
    protected function __construct($host, $prefix)
    {
        if (preg_match('/([^:]+):([0-9]+)/', $host, $matches)) {
            $this->host = $matches[1];
            $this->port = (integer)$matches[2];
        } else {
            $this->host = $host;
            $this->port = 6379;
        }
        $this->prefix = $prefix . '::';
        $this->hasBeenInitialized = false;
    }

    /**
     * Connects to redis.
     *
     * @return void
     */
    protected function init()
    {
        if (!$this->hasBeenInitialized) {
            $this->hasBeenInitialized = true;
            $this->redis = $this->createRedisClient();
        }
    }

    /**
     * @return \Redis
     */
    protected function createRedisClient()
    {
        $redis = new \Redis();
        $redis->connect($this->host, $this->port);
        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
        $redis->setOption(\Redis::OPT_PREFIX, $this->prefix);
        return $redis;
    }

}
