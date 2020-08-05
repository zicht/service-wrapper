<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Http\Observer;

use Zicht\Service\Common\Observers\ServiceObserverAdapter;
use Zicht\Service\Common\ServiceCallInterface;

/**
 * Adds REST request logging to the service call
 *
 * @deprecated Remove in next major version
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
