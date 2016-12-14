<?php

use Zicht\Service\Common\ServiceCallInterface;
use Zicht\Service\Common\ServiceObserverInterface;
use Zicht\Service\Common\ServiceWrapper;

require_once __DIR__ . '/../vendor/autoload.php';


class MyService
{
    public function doIt($name)
    {
        return sprintf("Hi, %s!\n", $name);
    }
}

class MyObserver implements ServiceObserverInterface
{
    public function notifyBefore(ServiceCallInterface $call)
    {
        if ($call->getRequest()->getParameterDeep([0]) == 'Smithers') {
            $call->getResponse()->setResponse(sprintf("%s, eh?\n", $call->getRequest()->getParameterDeep([0])));
            $call->cancel($this);
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
        $response = $call->getResponse();
        $response->setResponse(str_replace('Hi', 'Hello', $response->getResponse()));
    }
}

$wrapper = new ServiceWrapper(new MyService());
$wrapper->registerObserver(new MyObserver());

echo $wrapper->doIt("Bart"); // output: "Hello, Bart Simpson!"
echo $wrapper->doIt("Lisa"); // output: "Hello, Lisa Simpson!"
echo $wrapper->doIt("Smithers"); // output: "Smithers, eh?"

