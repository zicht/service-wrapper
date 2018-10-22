<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use Zicht\Service\Common\ServiceCallInterface;
use Zicht\Service\Common\ServiceObserver;

/**
 * Adapter for the ServiceObserver interface
 *
 * @package Zicht\Service\Common\Observers
 * @codeCoverageIgnore
 */
class ServiceObserverAdapter implements ServiceObserver
{
    /**
     * {@inheritdoc}
     */
    public function alterRequest(ServiceCallInterface $call)
    {
    }


    /**
     * {@inheritdoc}
     */
    public function alterResponse(ServiceCallInterface $call)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function notifyBefore(ServiceCallInterface $call)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function notifyAfter(ServiceCallInterface $call)
    {
    }
}
