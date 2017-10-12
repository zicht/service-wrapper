<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

/**
 * Helper to construct cache keys based on various attributes.
 *
 * The implementation does it's best to have a very descriptive name, so the keys tell as much as they can about
 * their contents.
 */
final class CacheKey implements CacheKeyInterface
{
    private $readableDepth;
    private $name;
    private $attributes;

    /**
     * Helper function to construct a readable string out of an array:
     *
     * If all items are either:
     * - Scalar, or
     * - Recursively if allowdepth > 1s
     *
     * @param array $array
     * @param int $allowDepth
     *
     * @return bool
     */
    private static function isAllScalar($array, $allowDepth)
    {
        return is_array($array) && array_reduce(
            $array,
            function ($v, $m) use ($allowDepth) {
                return $v && (
                    is_scalar($m)
                    || ($allowDepth > 1 && self::isAllScalar($m, $allowDepth -1))
                );
            },
            true
        );
    }

    /**
     * Construct the key with the passed name as a namespace (such as service method name)
     *
     * @param string $name
     * @param int $readableDepth
     */
    public function __construct($name, $readableDepth = 1)
    {
        $this->name = strtolower($name);
        $this->readableDepth = $readableDepth;

        $this->attributes = [];
    }


    /**
     * Add an attribute to the key. Attributes distinct the key, i.e. adding an attribute will ultimately
     * change the key's rendered name.
     *
     * @param string $name
     * @param mixed $value
     */
    public function addAttribute($name, $value)
    {
        $this->attributes[$name]= $value;
    }


    /**
     * Return the string key representation.
     *
     * The implementation works as follows:
     *
     * Use the name as a prefix. Then,
     *
     * @return string
     */
    public function getKey()
    {
        $keys = array_keys($this->attributes);
        $i = 0;
        return array_reduce(
            array_values($this->attributes),
            function ($ret, $attribute) use ($keys, &$i) {
                // sort array by key, to ensure that the cache key is the same for all similar requests
                if (is_array($attribute)) {
                    ksort($attribute);
                }
                if (self::isAllScalar($attribute, $this->readableDepth)) {
                    $strAttribute = str_replace(['"', "\n"], '', json_encode($attribute));
                } elseif (!is_scalar($attribute)) {
                    $strAttribute = sha1(json_encode($attribute));
                } else {
                    $strAttribute = (string)$attribute;
                }

                return sprintf('%s::%s', $ret, sprintf('%s:%s', $keys[$i ++], $strAttribute));
            },
            $this->name
        );
    }


    /**
     * Returns a string version of the key. Quotes are removed to get rid of
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getKey();
    }
}
