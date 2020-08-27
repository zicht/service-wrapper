<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Zicht\Service\Common\RequestInterface;
use Zicht\Service\Common\ServiceCallInterface;
use Zicht\Service\Common\ServiceWrapper;

class StatisticsObserver extends ServiceObserverAdapter implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * @param ServiceCallInterface $call
     * @return void
     */
    public function notifyAfter(ServiceCallInterface $call)
    {
        $request = $call->getRequest();
        $response = $call->getResponse();
        $this->logger->log(
            LogLevel::DEBUG,
            'Stats',
            [
                'method' => $request->getMethod(),
                'parameters' => $request->getParameters(),
                'cancelled' => (int)$call->isCancelled(),
                'cachable' => (int)$response->isCachable(),
                'error' => (int)$response->isError(),
            ]
        );
    }
}
