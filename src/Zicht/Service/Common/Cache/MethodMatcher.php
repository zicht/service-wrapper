<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

use Zicht\Service\Common\RequestInterface;

/**
 * Match a request based on an array configuration containing the method name and time to live.
 */
class MethodMatcher extends ArrayMatcher
{
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
     *     [method] => Array
     *         (
     *             [fallback] => [value => 10, error => 10, grace => 10],
     *             [attributes] => Array
     *                 (
     *                     [attributeA] => [value => 10, error => 10, grace => 10],
     *                     [attributeB] => [value => 10, error => 10, grace => 10],
     *                     [attributeC] => [value => 10, error => 10, grace => 10],
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
        parent::__construct($config);
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
}
