<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Http\Observer;

use Zicht\Service\Common\Observers\ServiceObserverAdapter;
use Zicht\Service\Common\ServiceCallInterface;

/**
 * Adds REST request logging to the service call
 *
 * @package Zicht\Service\Common\Soap\Observer
 */
class VerboseLoggingObserver extends ServiceObserverAdapter
{
    /**
     * {@inheritdoc}
     */
    public function notifyAfter(ServiceCallInterface $call)
    {
        $call->addLogAttributes(
            [
                'params' => $call->getRequest()->getParameters(),
            ]
        );
    }
}
