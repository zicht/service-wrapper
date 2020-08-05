<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

/**
 * Base interface for service calls initiated by the ServiceWrapper
 *
 */
interface ServiceCallInterface
{
    /**
     * @return ServiceWrapper
     */
    public function getService();

    /**
     * @return RequestInterface
     */
    public function getRequest();

    /**
     * @return ResponseInterface
     */
    public function getResponse();

    /**
     * Cancel the call
     *
     * The service request is not performed and Observers may alter their behavior by checking $call->isCancelled().
     * This only has effect in the notifyBefore() call.
     *
     * @param mixed $by
     * @return void
     */
    public function cancel($by);

    /**
     * Check if the event was cancelled
     *
     * @param mixed $className
     * @return bool
     */
    public function isCancelled($className = null);

    /**
     * Returns an array of classes responsible for cancelling the event.
     *
     * @return array
     */
    public function getCancelledBy();

    /**
     * Returns true when the call is made in terminate mode.
     *
     * @return bool
     */
    public function isTerminating();

    /**
     * Attributes to store in the log.
     *
     * @return array
     */
    public function getLogAttributes();

    /**
     * Adds log attributes to the call.
     *
     * @param mixed[] $attributes
     * @return void
     */
    public function addLogAttributes(array $attributes);

    /**
     * Checks if there is a parent call.
     *
     * @return bool
     */
    public function hasParent();

    /**
     * A parent (if there is one).
     *
     * This contains the parent call, if the current call triggers a new one.
     *
     * @return null|ServiceCallInterface
     */
    public function getParent();
}
