<?php declare(strict_types=1);
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

/**
 * Observer interface to hook into the service method execution.
 *
 * notifyBefore() is called before a service request is executed. The observer can alter the parameters used
 * in the request. If the notifyBefore() returns boolean false, the request is not executed, but all other observers
 * will be notified nonetheless.
 *
 * notifyAfter() is called after the request has been executed. If a fault occurred, it is passed to the observer so
 * the observer knows that the request failed. The fault can, however, be altered by the observer. The response will
 * be passed as well, so the observer can modify the response.
 *
 * If the requested was cancelled, the classname of the observers that cancelled the request are available in the
 * $isCancelledBy parameter, as an array.
 */
interface ServiceObserverInterface
{
    /**
     * An opportunity to change the request
     *
     * @return mixed
     */
    public function alterRequest(ServiceCallInterface $call);

    /**
     * An opportunity to change the response
     *
     * @return mixed
     */
    public function alterResponse(ServiceCallInterface $call);

    /**
     * Notify the observer before the request will be executed.
     *
     * @return void
     */
    public function notifyBefore(ServiceCallInterface $call);

    /**
     * Notify the observer that the request has been executed, or cancelled, and give the observer the opportunity to
     * alter the response and the fault.
     *
     * @return void
     */
    public function notifyAfter(ServiceCallInterface $call);

    /**
     * An opportunity to perform actions just before the application terminates
     *
     * @return void
     */
    public function terminate();
}
