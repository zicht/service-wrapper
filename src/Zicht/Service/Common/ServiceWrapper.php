<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

/**
 * Class ServiceWrapper
 *
 * @package Zicht\Service\Common
 */
class ServiceWrapper
{
    /**
     * The set of observers notified of any call to the Soap service
     *
     * @var ServiceObserver[]
     */
    private $observers = array();

    /**
     * @var array
     */
    private $callStack = array();

    /**
     * The logger instance to delegate to the observers (if they are LoggerAwareInterface instances)
     *
     * @var \Psr\Log\LoggerInterface
     */
    private $logger = null;


    /**
     * The wrapped service
     *
     * @var mixed
     */
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
     * @param ServiceObserver $observer
     * @param int $index
     * @return void
     */
    public function registerObserver(ServiceObserver $observer, $index = null)
    {
        if ($this->logger && $observer instanceof Observers\LoggerAwareInterface) {
            $observer->setLogger($this->logger);
        }
        $this->observers[] = $observer;
    }

    /**
     * Returns a tuple of `index` and observer `instance`. The index can be used to pass to `registerObserver` to
     * put it back at the index where it was.
     *
     * The use case is that some implementation knows about an observer being incompatible with some kind of
     * situation, so the observer needs to be temporarily unregistered and restored.
     *
     * @param string $className
     * @return array
     */
    public function unregisterObserver($className)
    {
        foreach ($this->observers as $idx => $observer) {
            if ($observer instanceof $className) {
                array_splice($this->observers, $idx, 1, []);
                return [$idx, $observer];
            }
        }
        return null;
    }


    /**
     * @return ServiceObserver[]
     */
    public function getObservers()
    {
        return $this->observers;
    }


    /**
     * Call a service, and notify all of the observers of the service being called. Each of the observers
     * can cancel a request by calling the cancel() method of the message that gets passed within the notifyBefore()
     * method. Within the notifyBefore() and notifyAfter() methods, the observer can influence the response and/or fault
     * rendered by the service call.
     *
     * @param string $methodName
     * @param array $args
     * @return mixed
     *
     * @throws \Exception
     */
    final public function __call($methodName, $args)
    {
        if (count($this->callStack)) {
            $parent = $this->callStack[count($this->callStack) -1];
        } else {
            $parent = null;
        }
        $call = $this->createServiceCall($methodName, $args, $parent);
        $this->callStack[] = $call;

        /** @var ServiceObserver[] $observers */
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
        array_pop($this->callStack);

        if ($call->getResponse()->isError()) {
            $fault = $call->getResponse()->getError();
            throw $fault;
        }

        return $call->getResponse()->getResponse();
    }

    /**
     * Register a logger with the service.
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @param array $raisedLogLevels
     * @return void
     */
    public function setLogger($logger, $raisedLogLevels = array())
    {
        $this->logger = $logger;
        // update the observer stack.
        foreach ($this->observers as $observer) {
            if ($observer instanceof Observers\LoggerAwareInterface) {
                $observer->setLogger($this->logger);
            }
        }
        $logger = new Observers\Logger();
        $logger->setRaisedLogLevels($raisedLogLevels);
        $this->registerObserver($logger);
    }

    /**
     * @{inheritDoc}
     */
    protected function execute(ServiceCallInterface $call)
    {
        return call_user_func_array(
            array($this->service, $call->getRequest()->getMethod()),
            $call->getRequest()->getParameters()
        );
    }

    /**
     * Creates a service call object.
     *
     * @param string $methodName
     * @param array $args
     * @param ServiceCall $parent
     * @return ServiceCall
     */
    protected function createServiceCall($methodName, $args, $parent = null)
    {
        return new ServiceCall(
            $this,
            new Request($methodName, $args),
            new Response(),
            $parent
        );
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
