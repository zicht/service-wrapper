<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Soap\Observer;

use \Zicht\Service\Common\Observers\ServiceObserverAdapter;
use \Zicht\Service\Common\ServiceCallInterface;

/**
 * Adds SOAP request logging to the service call
 *
 * @package Zicht\Service\Common\Soap\Observer
 */
class VerboseLoggingObserver extends ServiceObserverAdapter
{
    /**
     * @{inheritDoc}
     */
    public function notifyAfter(ServiceCallInterface $call)
    {
        $call->addLogAttributes(array(
            'request'   => $call->getService()->getWrappedService()->__getLastRequest(),
            'response'  => $call->getService()->getWrappedService()->__getLastResponse()
        ));
    }
}