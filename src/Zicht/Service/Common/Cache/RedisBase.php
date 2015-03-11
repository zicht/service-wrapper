<?php

namespace Zicht\Service\Common\Cache;

/**
 * Base class for redis-based storage
 */
class RedisBase
{
    private $host;
    private $prefix;
    private $inited = false;

    /**
     * @var \Redis
     */
    protected $redis;

    protected function __construct($host, $prefix)
    {
        $this->host = $host;
        $this->prefix = $prefix;
    }

    /**
     * Connects to redis.
     */
    protected function init()
    {
        if (!$this->inited) {
            $this->inited = true;
            $this->redis = new \Redis();
            $this->redis->connect($this->host);
            $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
            $this->redis->setOption(\Redis::OPT_PREFIX, $this->prefix);
        }
    }
}
