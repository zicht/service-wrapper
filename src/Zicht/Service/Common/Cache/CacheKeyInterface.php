<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */


namespace Zicht\Service\Common\Cache;


interface CacheKeyInterface
{
    /**
     * @return string
     */
    public function getKey();
}