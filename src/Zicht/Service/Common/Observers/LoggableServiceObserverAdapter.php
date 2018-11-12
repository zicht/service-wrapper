<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

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
     * {@inheritdoc}
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
    public function addLogRecord($level, $message, array $context = [])
    {
        if ($this->logger) {
            $this->logger->addRecord($level, $message, $context);
        }
    }
}
