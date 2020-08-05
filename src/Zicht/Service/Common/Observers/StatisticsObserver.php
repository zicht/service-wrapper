<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use Zicht\Service\Common\RequestInterface;
use Zicht\Service\Common\ServiceCallInterface;
use Zicht\Service\Common\ServiceWrapper;

class StatisticsObserver extends LoggableServiceObserverAdapter
{
    /** @var string[] */
    protected $statistics = [
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * @param ServiceCallInterface $call
     * @return void
     */
    public function notifyAfter(ServiceCallInterface $call)
    {
        $this->statistics[$call->isCancelled() ? 'cancelled' : 'completed'] [] = $call->getRequest()->getMethod();
    }

    /**
     * If a cache hit has occurred for a key that has a grace-period, the cache will be refreshed if needed.
     *
     * @return void
     */
    public function terminate()
    {
        if (!empty($this->statistics['completed']) || !empty($this->statistics['cancelled'])) {
            $this->addLogRecord(self::INFO, 'StatisticsObserver', $this->statistics);
        }
    }
}
