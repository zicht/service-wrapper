<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use \Zicht\Service\Common\ServiceCallInterface;
use \Zicht\Service\Common\ServiceObserver;

/**
 * Adapter for the ServiceObserver interface
 *
 * @package Zicht\Service\Common\Observers
 */
class ServiceObserverAdapter implements ServiceObserver
{
    /**
     * @{inheritDoc}
     */
    public function notifyBefore(ServiceCallInterface $call)
    {
    }

    /**
     * @{inheritDoc}
     */
    public function notifyAfter(ServiceCallInterface $call)
    {
    }
}