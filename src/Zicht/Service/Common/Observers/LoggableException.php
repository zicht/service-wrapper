<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

/**
 * If a domain exception is loggable, it can tell the Logger observer to use a specific log level for logging the fault.
 *
 * It conveniently extends the Loggable interface to inherit log levels.
 * @deprecated Remove in next major version
 */
interface LoggableException
{
    /**
     * Return the log level to use for this Exception
     *
     * @return int
     */
    public function getLogLevel();
}
