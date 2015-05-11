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
    /**
     * @var
     */
    private $host;
    /**
     * @var
     */
    private $prefix;
    /**
     * @var bool
     */
    private $inited = false;

    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * Constructor.
     *
     * @param string $host
     * @param string $prefix
     */
    public function __construct($host, $prefix)
    {
        $this->host = $host;
        $this->prefix = $prefix;
    }

    /**
     * Connects to redis.
     *
     * @return void
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
