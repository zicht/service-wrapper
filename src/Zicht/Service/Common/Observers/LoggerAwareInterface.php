<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use Zicht\Service\Common\LoggerConstants;

/**
 * Interface LoggerAwareInterface
 *
 * @package Zicht\Service\Common\Observers
 */
interface LoggerAwareInterface extends LoggerConstants
{
    /**
     * Logger implementation
     *
     * @param mixed $logger
     * @return mixed
     */
    public function setLogger($logger);
}
