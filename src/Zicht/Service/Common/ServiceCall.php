<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

/**
 * Represents a service call.
 *
 * @package Zicht\Service\Common
 */
class ServiceCall implements ServiceCallInterface
{
    private $service = null;
    private $request = null;
    private $response = null;
    private $cancelled = array();
    private $logAttributes = array();

    /**
     * Construct the service call
     *
     * @param ServiceWrapper $service
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param ServiceCallInterface|null $parent
     */
    public function __construct(ServiceWrapper $service, RequestInterface $request, ResponseInterface $response, $parent)
    {
        $this->service = $service;
        $this->request = $request;
        $this->response = $response;
        $this->parent = $parent;
    }

    /**
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Attributes to store in the log.
     *
     * @return array
     */
    public function getLogAttributes()
    {
        return $this->logAttributes;
    }

    /**
     * Cancel the call, i.e. don't do the request. Only has effect in the notifyBefore() call.
     *
     * @param mixed $by
     * @return void
     */
    public function cancel($by)
    {
        $this->cancelled[]= (is_object($by) ? get_class($by) : (string)$by);
    }

    /**
     * Checks if the event was cancelled.
     *
     * @param null $by
     * @return bool
     */
    public function isCancelled($by = null)
    {
        if (!is_null($by)) {
            return in_array($by, $this->cancelled);
        }
        return count($this->cancelled) > 0;
    }

    /**
     * Returns an array of what classes were responsible for cancelling the request
     *
     * @return array
     */
    public function getCancelledBy()
    {
        return $this->cancelled;
    }

    /**
     * @{inheritDoc}
     */
    public function hasParent()
    {
        return null !== $this->parent;
    }

    /**
     * @{inheritDoc}
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return null|\Zicht\Service\Common\ServiceWrapper
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @{inheritDoc}
     */
    public function addLogAttributes(array $attributes)
    {
        $this->logAttributes = array_merge_recursive($this->logAttributes, $attributes);
    }
}