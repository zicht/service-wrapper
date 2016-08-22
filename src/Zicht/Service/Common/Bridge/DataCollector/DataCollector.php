<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */
namespace Zicht\Service\Common\Bridge\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector as BaseCollector;

/**
 * Data collector for calls to the wrapped service
 */
class DataCollector extends BaseCollector
{
    /**
     * @{inheritDoc}
     */
    public function __construct(Observer $observer)
    {
        $this->observer = $observer;
    }

    /**
     * @{inheritDoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = ['calls' => $this->observer->getCalls()];
    }

    /**
     * Returns a list of calls with meta data
     *
     * @return array
     */
    public function getCalls()
    {
        return array_map(
            function ($c) {
                $c['t_delta'] = -1;
                $c['mem_delta'] = -1;

                if (isset($c['t_end']) && isset($c['t_start'])) {
                    $c['t_delta'] = $c['t_end'] - $c['t_start'];
                }
                if (isset($c['mem_end']) && isset($c['mem_start'])) {
                    $c['mem_delta'] = $c['mem_end'] - $c['mem_start'];
                }
                return $c;
            },
            $this->data['calls']
        );
    }


    /**
     * Get a summary for collected data
     *
     * @return string
     */
    public function getSummary()
    {
        $ret = sprintf(
            "%d call(s) in %d ms\n%d error(s)",
            $this->getCallCount(),
            $this->getTimeSpent(),
            $this->getErrorCount()
        );

        return $ret;
    }


    /**
     * Get the total time spent inside the service call.
     *
     * @return float
     */
    public function getTimeSpent()
    {
        return floor(
            array_sum(
                array_map(
                    function ($call) {
                        return $call['t_end'] - $call['t_start'];
                    },
                    $this->data['calls']
                )
            ) * 1000
        );
    }


    /**
     * Return the number of calls executed
     *
     * @return int
     */
    public function getCallCount()
    {
        return count($this->data['calls']);
    }

    /**
     * Return the number of calls that resulted in an error
     *
     * @return mixed
     */
    public function getErrorCount()
    {
        return array_reduce(
            $this->data['calls'],
            function ($t, $c) {
                if ($c['is_error']) {
                    return $t +1;
                }
                return $t;
            },
            0
        );
    }

    /**
     * @{inheritDoc}
     */
    public function getName()
    {
        return 'service';
    }
}
