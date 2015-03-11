<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

/**
 * Interface LoggerAwareInterface
 *
 * @package Zicht\Service\Common\Observers
 */
interface LoggerAwareInterface
{
    /**
     * Logger implementation
     *
     * @param mixed $logger
     * @return mixed
     */
    public function setLogger($logger);
}