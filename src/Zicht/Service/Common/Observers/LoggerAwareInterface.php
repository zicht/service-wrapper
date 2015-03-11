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
    const DEBUG         = 100;
    const INFO          = 200;
    const NOTICE        = 250;
    const WARNING       = 300;
    const ERROR         = 400;
    const CRITICAL      = 500;
    const ALERT         = 550;
    const EMERGENCY     = 600;

    /**
     * Logger implementation
     *
     * @param mixed $logger
     * @return mixed
     */
    public function setLogger($logger);
}