<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

use \Zicht\Service\Common\RequestInterface;

/**
 * Match a request based on an array configuration containing the method name and time to live.
 */
class MethodMatcher implements RequestMatcher
{
    /**
     * The entities with their default time to live and optionally attribute specific time to lives
     *
     * @var array
     */
    protected $config = array();

    /**
     * Construct a method matcher which allows for specific parameters of the method to be matched.
     *
     * The parameter spec contains an array of arrays, with each of the entries being a pair of:
     * - path to the parameter (used for getParameterDeep() in the Request)
     * - the value to match on
     *
     * The configuration must take the structure as seen below
     * Array
     * (
     *     [METHOD] => Array
     *         (
     *             [default] => 10
     *             [attributes] => Array
     *                 (
     *                     [five] => 5
     *                 )
     *             [parameters] => Array
     *                 (
     *
     *                     [0] => Array
     *                         (
     *                             [0] => Array
     *                                 (
     *                                     [0] => 0,
     *                                     [1] => 'ForceUpdate'
     *                                 )
     *                             [1] => false
     *                         )
     *                 )
     *         )
     * )
     *
     * @param array $config
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
        $key = array(
            $request->getMethod(),
            md5(serialize($request->getParameters()))
        );
        foreach ($this->config[strtolower($request->getMethod())]['attributes'] as $attribute => $ttl) {
            $key []= md5(serialize($request->getAttribute($attribute)));
        }
        return strtolower(join('.', $key));
    }

    /**
     * Return if the current request matcher is a candidate for the specified request
     *
     * @param \Zicht\Service\Common\RequestInterface $request
     * @return bool
     */
    public function isMatch(RequestInterface $request)
    {
        foreach ($this->config as $method => $config) {
            if ($request->isMethod($method)) {
                foreach ($config['parameters'] as $spec) {
                    list($path, $value) = $spec;
                    if ($request->getParameterDeep($path) !== $value) {
                        return false;
                    }
                }
                return true;
            }
        }
        return false;
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
     * @param \Zicht\Service\Common\RequestInterface $request
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