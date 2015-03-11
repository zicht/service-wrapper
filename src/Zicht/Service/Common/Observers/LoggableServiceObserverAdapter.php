<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use \Monolog\Logger;

/**
 * Base class for observers that do logging.
 *
 * @package Zicht\Service\Common\Observers
 */
class LoggableServiceObserverAdapter extends ServiceObserverAdapter implements LoggerAwareInterface
{
    /**
     * @var Logger
     */
    protected $logger = null;

    /**
     * @{inheritDoc}
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }


    /**
     * Add a log record to the logger, if the logger was initialized
     *
     * @param int $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function addLogRecord($level, $message, array $context = array())
    {
        if ($this->logger) {
            $this->logger->addRecord($level, $message, $context);
        }
    }
}