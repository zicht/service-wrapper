<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

/**
 * Interface for objects that generate their own cache key
 */
interface CacheKeyInterface
{
    /**
     * @return string
     */
    public function getKey();
}
