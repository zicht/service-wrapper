<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

/**
 * Represents a service call.
 *
 */
class ServiceCall implements ServiceCallInterface
{
    /** @var ServiceWrapper */
    private $service;

    /** @var RequestInterface */
    private $request;

    /** @var ResponseInterface */
    private $response;

    /** @var ServiceCallInterface|null */
    private $parent;

    /** @var bool */
    private $terminating = false;

    /** @var array */
    private $cancelled = [];

    /** @var array */
    private $info = [];

    /**
     * @param ServiceWrapper $service
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param ServiceCallInterface|null $parent
     * @param bool $terminating
     */
    public function __construct(ServiceWrapper $service, RequestInterface $request, ResponseInterface $response, $parent = null, $terminating = false)
    {
        $this->service = $service;
        $this->request = $request;
        $this->response = $response;
        $this->parent = $parent;
        $this->terminating = $terminating;
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
     * Cancel the call
     *
     * The service request is not performed and Observers may alter their behavior by checking $call->isCancelled().
     * This only has effect in the notifyBefore() call.
     *
     * @param mixed $by
     * @return void
     */
    public function cancel($by)
    {
        $this->cancelled[] = (is_object($by) ? get_class($by) : (string)$by);
    }

    /**
     * Check if the event was cancelled
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
     * Returns an array of classes responsible for cancelling the event.
     *
     * @return array
     */
    public function getCancelledBy()
    {
        return $this->cancelled;
    }

    /**
     * Returns true when the call is made in terminate mode.
     *
     * @return bool
     */
    public function isTerminating()
    {
        return $this->terminating;
    }

    /**
     * {@inheritdoc}
     */
    public function getInfo(string $key, $fallback = null)
    {
        return $this->info[$key] ?? $fallback;
    }

    /**
     * {@inheritdoc}
     */
    public function setInfo(string $key, $info)
    {
        return $this->info[$key] = $info;
    }

    /**
     * {@inheritdoc}
     */
    public function hasParent()
    {
        return null !== $this->parent;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return ServiceWrapper
     */
    public function getService()
    {
        return $this->service;
    }
}
