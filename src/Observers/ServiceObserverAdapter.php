<?php declare(strict_types=1);
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use Zicht\Service\Common\ServiceCallInterface;
use Zicht\Service\Common\ServiceObserverInterface;

/**
 * Adapter for the ServiceObserver interface
 *
 * @codeCoverageIgnore
 */
class ServiceObserverAdapter implements ServiceObserverInterface
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

    /**
     * {@inheritdoc}
     */
    public function terminate()
    {
    }
}
