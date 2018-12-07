<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Bridge\DataCollector;

use Zicht\Service\Common\Observers\Cache;
use Zicht\Service\Common\Observers\ServiceObserverAdapter;
use Zicht\Service\Common\ServiceCallInterface;

class Observer extends ServiceObserverAdapter
{
    protected $i = 0;
    protected $calls = [];

    /**
     * {@inheritdoc}
     */
    public function notifyBefore(ServiceCallInterface $call)
    {
        $this->calls[spl_object_hash($call)] = [
            'method' => $call->getRequest()->getMethod(),
            'params' => $call->getRequest()->getParameters(),
            't_start' => microtime(true),
            'mem_start' => memory_get_usage(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function notifyAfter(ServiceCallInterface $call)
    {
        $message = null;

        if ($call->getResponse()->isError()) {
            $error = $call->getResponse()->getError();
            if ($error instanceof \Exception) {
                $message = sprintf('%s (%s)', $error->getMessage(), get_class($error));
            } elseif (is_object($error)) {
                $message = sprintf('Unknown error (%s)', get_class($error));
            } else {
                $message = $error;
            }
        }

        $this->calls[spl_object_hash($call)] = [
            'response' => $call->getResponse()->getResponse(),
            'cancelledBy' => $call->getCancelledBy(),
            'error' => $message,
            'is_error' => $call->getResponse()->isError(),
            'is_cached' => $call->isCancelled(Cache::class),
            't_end' => microtime(true),
            'mem_end' => memory_get_usage()
        ] + $this->calls[spl_object_hash($call)];
    }

    /**
     * @return ServiceCallInterface[]
     */
    public function getCalls()
    {
        return $this->calls;
    }
}
