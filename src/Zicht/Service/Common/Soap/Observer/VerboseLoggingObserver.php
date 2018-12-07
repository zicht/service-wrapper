<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Soap\Observer;

use Zicht\Service\Common\Observers\ServiceObserverAdapter;
use Zicht\Service\Common\ServiceCallInterface;

/**
 * Adds SOAP request logging to the service call
 *
 */
class VerboseLoggingObserver extends ServiceObserverAdapter
{
    /**
     * {@inheritdoc}
     */
    public function notifyAfter(ServiceCallInterface $call)
    {
        $wrappedService = $call->getService()->getWrappedService();
        $call->addLogAttributes(
            [
                'request'   => $wrappedService->__getLastRequest(),
                'response'  => $wrappedService->__getLastResponse()
            ]
        );
    }
}
