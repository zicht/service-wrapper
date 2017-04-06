<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

use Zicht\Service\Common\RequestInterface;

/**
 * Match a request based on an array configuration containing the method name and time to live.
 */
class ArrayMatcher implements RequestMatcher
{
    /**
     * The methods with their default time to live and optionally attribute specific time to lives
     *
     * @var array
     */
    protected $config = array();

    /**
     * Construct a basic array matcher that specifies methods as keys and TTL's as values. Each method call is
     * hashed based on it's exact parameters (serialized + md5) and the method name.
     *
     * The configuration must take the structure as seen below
     * Array
     * (
     *     [methodA] => Array
     *         (
     *             [default] => 10
     *             [attributes] => Array
     *                 (
     *                     [five] => 5
     *                 )
     *         )
     * )
     *
     * @param array $config
     * @throws \InvalidArgumentException
     */
    public function __construct(array $config)
    {
        foreach ($config as $method => $properties) {
            $this->config[strtolower($method)] = $properties;
        }
    }


    /**
     * Generates a cache storage key for the current request
     *
     * @param RequestInterface $request
     * @return string
     */
    public function getKey(RequestInterface $request)
    {
        $key = new CacheKey($request->getMethod());
        foreach ($request->getParameters() as $paramName => $paramValue) {
            $key->addAttribute($paramName, $paramValue);
        }
        foreach ($this->config[strtolower($request->getMethod())]['attributes'] as $attrName => $ttl) {
            if ($attrValue = $request->getAttributeDeep(explode(".", $attrName))) {
                $key->addAttribute($attrName, $attrValue);
            }
        }
        return $key;
    }


    /**
     * Return if the current request matcher is a candidate for the specified request
     *
     * @param RequestInterface $request
     * @return bool
     */
    public function isMatch(RequestInterface $request)
    {
        return array_key_exists(strtolower($request->getMethod()), $this->config);
    }


    /**
     * @{inheritDoc}
     */
    public function isExpunger(RequestInterface $request)
    {
        return false;
    }


    /**
     * Return the time to live (in seconds) for the specified request
     *
     * @param RequestInterface $request
     * @return int
     */
    public function getTtl(RequestInterface $request)
    {
        $config = $this->config[strtolower($request->getMethod())];

        $ttls = array($config['default']);
        foreach ($config['attributes'] as $attribute => $ttl) {
            if ($request->hasAttribute($attribute)) {
                $ttls []= $ttl;
            }
        }
        return min($ttls);
    }
}
