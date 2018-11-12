<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use Zicht\Service\Common\LoggerConstants;

/**
 * Times requests and logs them according to a map that specifies the log levels to use per threshold of execution
 * time in milliseconds.
 */
class Timer
{
    /**
     * Default timings => loglevel mappings
     *
     * @var array
     */
    public static $defaults = [
        0 => LoggerConstants::DEBUG,
        1500 => LoggerConstants::INFO,
        4000 => LoggerConstants::WARNING,
    ];

    /**
     * When to log what; if the number of milliseconds is larger than the threshold (the key of the array), the
     * loglevel as specified in the value of the array is used.
     *
     * @var array
     */
    protected $timings = [];


    protected $map = [];


    /**
     * Construct the timer
     *
     * @param array $map
     */
    public function __construct(array $map = null)
    {
        if (is_null($map)) {
            $map = self::$defaults;
        }
        $this->map = $map;
        ksort($this->map);
    }


    /**
     * Record the start time of the request.
     *
     * @param string $serviceMethod
     * @return void
     */
    public function start($serviceMethod)
    {
        $this->timings[$serviceMethod] = $this->getCurrentTime();
    }


    /**
     * Record the elapsed time of the request.
     *
     * @param string $serviceMethod
     * @return mixed
     */
    public function stop($serviceMethod)
    {
        if (isset($this->timings[$serviceMethod])) {
            $stop = $this->getCurrentTime();
            $start = $this->timings[$serviceMethod];
            unset($this->timings[$serviceMethod]);
            return $stop - $start;
        }
        return -1;
    }


    /**
     * Returns the log level based on the timing.
     *
     * @param int $time
     * @return int
     */
    public function getLogLevel($time)
    {
        foreach (array_reverse(array_keys($this->map)) as $threshold) {
            if ($time * 1000 >= $threshold) {
                return $this->map[$threshold];
            }
        }
        return LoggerConstants::DEBUG;
    }


    /**
     * Returns the current time.
     *
     * @return mixed
     */
    protected function getCurrentTime()
    {
        return microtime(true);
    }
}
