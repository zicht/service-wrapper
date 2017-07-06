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
    const DEFAULT_REDIS_PORT = 6379;

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
     * @param string $host
     * @param string $prefix
     * @param integer $port
     */
    public function __construct($host, $prefix, $port = null)
    {
        $this->host = $host;
        $this->port = (null === $port ? self::DEFAULT_REDIS_PORT : $port);
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
            $this->redis = new \Redis();
            $this->redis->connect($this->host, $this->port);
            $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
            $this->redis->setOption(\Redis::OPT_PREFIX, $this->prefix);
        }
    }
}
