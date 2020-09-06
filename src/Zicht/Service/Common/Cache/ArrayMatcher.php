<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

use Zicht\Service\Common\RequestInterface;

/**
 * Match a request based on an array configuration containing the method name and time to live.
 */
class ArrayMatcher implements RequestMatcher
{
    /** @var array The methods with their default time to live and optionally attribute specific time to lives */
    protected $config = [];

    /**
     * Construct a basic array matcher that specifies methods as keys and TTL's as values. Each method call is
     * hashed based on it's exact parameters (serialized + md5) and the method name.
     *
     * The configuration must take the structure as seen below
     * Array
     * (
     *     [method] => Array
     *         (
     *             [fallback] => [value => 10, error => 10, grace => 10],
     *             [attributes] => Array
     *                 (
     *                     [attribute] => [value => 10, error => 10, grace => 10],
     *                 )
     *         )
     * )
     *
     * @param array $config
     * @throws \InvalidArgumentException
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function getKey(RequestInterface $request)
    {
        $key = new CacheKey($request->getMethod());
        foreach ($request->getParameters() as $paramName => $paramValue) {
            $key->addAttribute($paramName, $paramValue);
        }
        foreach ($this->config[$request->getMethod()]['attributes'] as $attrName => $_) {
            if (($attrValue = $request->getAttributeDeep(explode('.', $attrName))) !== null) {
                $key->addAttribute($attrName, $attrValue);
            }
        }
        return $key->getKey();
    }

    /**
     * {@inheritdoc}
     */
    public function isMatch(RequestInterface $request)
    {
        return array_key_exists($request->getMethod(), $this->config);
    }

    /**
     * {@inheritdoc}
     */
    public function isExpunger(RequestInterface $request)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getTtlConfig(RequestInterface $request)
    {
        $config = $this->config[$request->getMethod()];

        // Compute base TTL for storing a value
        $valueTtls = [];
        $errorTtls = [];
        $graceTtls = [];
        foreach ($config['attributes'] as $attribute => $ttlConfig) {
            if ($request->hasAttribute($attribute)) {
                $valueTtls [] = $ttlConfig['value'];
                $errorTtls [] = $ttlConfig['error'];
                $graceTtls [] = $ttlConfig['grace'];
            }
        }

        $fallback = $config['fallback'];
        return [
            'value' => empty($valueTtls) ? $fallback['value'] : min($valueTtls),
            'error' => empty($errorTtls) ? $fallback['error'] : min($errorTtls),
            'grace' => empty($graceTtls) ? $fallback['grace'] : min($graceTtls),
        ];
    }
}
