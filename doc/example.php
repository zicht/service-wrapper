<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht online
 */
use Zicht\Service\Common\ServiceCallInterface;
use Zicht\Service\Common\ServiceObserverInterface;
use Zicht\Service\Common\ServiceWrapper;

require_once __DIR__ . '/../vendor/autoload.php';

// The service we're going to wrap
class MyService
{
    // Some service method.
    public function doIt($name)
    {
        if (!is_string($name)) {
            throw new \InvalidArgumentException("Need string!");
        }
        return sprintf("Hi, %s!\n", $name);
    }
}


// The observer we're going to attach to the service.
class MyObserver implements ServiceObserverInterface
{
    //
    public function notifyBefore(ServiceCallInterface $call)
    {
        switch ($call->getRequest()->getParameterDeep([0])) {
            case 'Smithers':
                // replace the response with something else.
                $call->getResponse()->setResponse(sprintf("%s, eh?\n", $call->getRequest()->getParameterDeep([0])));

                // call never gets executed.
                $call->cancel($this);
                break;
            case 'Sideshow Bob':
                $call->getResponse()->setError(new \Exception("That is no last name!"));
                break;
        }
    }

    public function notifyAfter(ServiceCallInterface $call)
    {
    }

    public function alterRequest(ServiceCallInterface $call)
    {
        if (in_array($call->getRequest()->getParameterDeep([0]), ['Lisa', 'Bart', 'Homer', 'Marge', 'Maggie'])) {
            $call->getRequest()->setParameterDeep([0], $call->getRequest()->getParameterDeep([0]) . ' Simpson');
        }
    }

    public function alterResponse(ServiceCallInterface $call)
    {
        if ($call->isCancelled()) {
            return;
        }
        if ($call->getResponse()->isError()) {
            $call->getResponse()->setError(null);
            $call->getResponse()->setResponse(":(\n");
        } else {
            $response = $call->getResponse();
            $response->setResponse(str_replace('Hi', 'Hello', $response->getResponse()));
        }
    }
}

$wrapper = new ServiceWrapper(new MyService());
$wrapper->registerObserver(new MyObserver());

echo $wrapper->doIt("Bart"); // output: "Hello, Bart Simpson!"
echo $wrapper->doIt("Lisa"); // output: "Hello, Lisa Simpson!"
echo $wrapper->doIt("Smithers"); // output: "Smithers, eh?"
echo $wrapper->doIt("Sideshow Bob"); // output: ":("
echo $wrapper->doIt(1); // output: ":("
