<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

class ServiceWrapper
{
    /** @var ServiceObserverInterface[] The set of observers notified of any call to the Soap service */
    private $observers = [];

    /** @var array */
    private $callStack = [];

    /** @var bool */
    private $terminating = false;

    /** @var mixed The wrapped service */
    private $service;

    /**
     * Constructs the facade based on the given service implementation
     *
     * @param mixed $service
     */
    public function __construct($service)
    {
        if ($service instanceof ServiceFactoryInterface) {
            $this->serviceFactory = $service;
            $this->service = null;
        } else {
            $this->serviceFactory = null;
            $this->service = $service;
        }
    }

    /**
     * Returns the wrapped service
     *
     * @return object
     */
    public function getWrappedService()
    {
        if (null === $this->service) {
            $this->factory();
        }

        return $this->service;
    }

    /**
     * Add an observer to the list of observers
     *
     * @param ServiceObserverInterface $observer
     * @return void
     */
    public function registerObserver(ServiceObserverInterface $observer)
    {
        $this->observers[] = $observer;
    }

    /**
     * Add one or more observers to the list of observers
     *
     * @param ServiceObserverInterface[] $observers
     * @return void
     */
    public function registerObservers(array $observers)
    {
        $this->observers = array_merge($this->observers, $observers);
    }

    /**
     * @return ServiceObserverInterface[]
     */
    public function getObservers()
    {
        return $this->observers;
    }

    /**
     * Call a service, and notify all of the observers of the service being called. Each of the observers
     * can cancel a request by calling the cancel() method of the message that gets passed within the notifyBefore()
     * method. Within the alterRequest() and alterResponse() methods, the observer can influence the response and/or
     * fault rendered by the service call.
     *
     * @param string $methodName
     * @param array $args
     * @return mixed
     * @throws \Exception
     */
    public function __call($methodName, $args)
    {
        if (count($this->callStack)) {
            $parent = $this->callStack[count($this->callStack) - 1];
        } else {
            $parent = null;
        }
        $call = $this->createServiceCall($methodName, $args, $parent, $this->terminating);
        $this->callStack[] = $call;

        // Ensure that we pop $call from the stack using try...finally...
        try {
            foreach ($this->observers as $observer) {
                $observer->alterRequest($call);
            }
            $call->getRequest()->freeze();
            foreach ($this->observers as $observer) {
                $observer->notifyBefore($call);
            }
            try {
                if (!$call->isCancelled()) {
                    $this->factory(); // initialize the service, if it was not yet initialized.
                    $call->getResponse()->setResponse($this->execute($call));
                }
            } catch (\Exception $exception) {
                // The SoapFault will be passed to the observers, so they can decide
                // what exception to throw
                $call->getResponse()->setError($exception);
            }

            foreach ($this->observers as $observer) {
                $observer->alterResponse($call);
            }
            $call->getResponse()->freeze();
            foreach ($this->observers as $observer) {
                $observer->notifyAfter($call);
            }

            if ($call->getResponse()->isError()) {
                $fault = $call->getResponse()->getError();
                throw $fault;
            }

            return $call->getResponse()->getResponse();
        } finally {
            array_pop($this->callStack);
        }
    }

    public function terminate()
    {
        $this->terminating = true;
        foreach ($this->observers as $observer) {
            $observer->terminate($this);
        }
    }

    /**
     * Perform the service call
     *
     * @param ServiceCallInterface $call
     * @return mixed
     */
    protected function execute(ServiceCallInterface $call)
    {
        return call_user_func_array(
            [$this->service, $call->getRequest()->getMethod()],
            $call->getRequest()->getParameters()
        );
    }

    /**
     * Creates a service call object.
     *
     * @param string $methodName
     * @param array $args
     * @param ServiceCall $parent
     * @param bool $terminating
     * @return ServiceCall
     */
    protected function createServiceCall($methodName, $args, $parent = null, $terminating = false)
    {
        return new ServiceCall($this, new Request($methodName, $args), new Response(), $parent, $terminating);
    }

    /**
     * Calls the factory to initialize the service if applicable.
     */
    private function factory()
    {
        if (null === $this->serviceFactory) {
            return;
        }

        $this->service = $this->serviceFactory->createService();
        $this->serviceFactory = null;
    }
}
