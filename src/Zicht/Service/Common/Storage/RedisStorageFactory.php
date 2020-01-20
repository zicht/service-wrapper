<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Storage;

class RedisStorageFactory
{
    /** @var string */
    protected $redisHost;

    /** @var int */
    protected $redisPort;

    /** @var string */
    protected $redisPrefix;

    /** @var null|\Redis */
    protected $redisClient;

    /**
     * Construct the cache, and use $cache as the cache container object.
     *
     * @param string $redisHost
     * @param int $redisPort
     * @param string $redisPrefix
     */
    public function __construct($redisHost, $redisPort, $redisPrefix)
    {
        $this->redisHost = $redisHost;
        $this->redisPort = $redisPort;
        $this->redisPrefix = $redisPrefix;
        $this->redisClient = null;
    }

    /**
     * @return \Redis
     */
    public function getClient()
    {
        if (null === $this->redisClient) {
            $this->redisClient = $this->createClient();
        }
        return $this->redisClient;
    }

    /**
     * @return \Redis
     */
    public function createClient()
    {
        $redisClient = new \Redis();
        $redisClient->connect($this->redisHost, $this->redisPort);
        $redisClient->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
        $redisClient->setOption(\Redis::OPT_PREFIX, sprintf('%s::', $this->redisPrefix));
        return $redisClient;
    }
}
