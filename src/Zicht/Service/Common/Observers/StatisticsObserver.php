<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Zicht\Service\Common\ServiceCallInterface;

class StatisticsObserver extends ServiceObserverAdapter implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function notifyBefore(ServiceCallInterface $call)
    {
        $call->setInfo('StatisticsObserver', ['t_start' => microtime(true), 'm_start' => memory_get_usage()]);
    }

    /**
     * {@inheritdoc}
     */
    public function notifyAfter(ServiceCallInterface $call)
    {
        $info = $call->getInfo('StatisticsObserver');
        $request = $call->getRequest();
        $response = $call->getResponse();
        $this->logger->log(
            LogLevel::DEBUG,
            'Stats',
            array_filter(
                array_merge(
                    $call->getInfo('ClientStatistics', []),
                    [
                        'cancelledBy' => $call->getCancelledBy(),
                        'errors' => ($error = $response->getError()) instanceof \Exception ? [$error->getMessage()] : null,
                        'isCancelled' => (int)$call->isCancelled(),
                        'isError' => (int)$response->isError(),
                        'memory' => memory_get_usage() - $info['m_start'],
                        'method' => $request->getMethod(),
                        'parameters' => $request->getParameters(),
                        'time' => microtime(true) - $info['t_start'],
                    ]
                )
            )
        );
    }
}
