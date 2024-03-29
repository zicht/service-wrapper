<?php declare(strict_types=1);
/**
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
    /** @var int */
    public const MAX_KEY_LENGTH = 512;

    /** @var string */
    private $name;

    /** @var array */
    private $attributes;

    /**
     * Construct the key with the passed name as a namespace (such as service method name)
     *
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
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
        $this->attributes[$name] = $value;
    }

    /**
     * Return the string key representation.
     *
     * The key consists of $this->name and $this->attributes, however,
     * if the key is too long, it will consist of $this->name and a hash.
     *
     * @return string
     */
    public function getKey()
    {
        $key = sprintf('%s:%s', $this->name, json_encode($this->attributes));
        return strlen($key) < self::MAX_KEY_LENGTH ? $key : sprintf('%s:%s', $this->name, sha1($key));
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
