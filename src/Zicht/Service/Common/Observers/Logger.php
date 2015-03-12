<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Observers;

use \Exception;
use \DOMDocument;
use \Zicht\Service\Common\ServiceCallInterface;

/**
 * The Logger is a logger utility to have request and response information logged in a Monolog\Logger implementation.
 */
class Logger extends LoggableServiceObserverAdapter
{
    protected $raisedLogLevels = array();

    /**
     * Constructs the Logger observer
     *
     * @param bool $enableTimer
     */
    public function __construct($enableTimer = true)
    {
        $this->timer = ($enableTimer ? new Timer() : null);
    }


    /**
     * Record the start time of the request.
     *
     * @param \Zicht\Service\Common\ServiceCallInterface $event
     * @return void
     */
    public function notifyBefore(ServiceCallInterface $event)
    {
        if (!$this->logger) {
            return;
        }

        $this->timer && $this->timer->start($event->getRequest()->getMethod());
    }


    /**
     * After performing the request the following info is logged:
     *
     * - If the request was cancelled by another observer, it is logged as a simple log row specifying who cancelled
     *   the request.
     * - At DEBUG level, the method, request body and response body are logged.
     * - If a fault occurred, the same info is logged at ERROR level, unless the fault defines it's own error level
     *
     * @param \Zicht\Service\Common\ServiceCallInterface $call
     * @return void
     */
    public function notifyAfter(ServiceCallInterface $call)
    {
        if (!$this->logger) {
            return;
        }

        $time = null;
        $logLevel = $this->getDefaultLogLevel($call);
        $msg = $call->getRequest()->getMethod() . ' done';

        if ($this->timer) {
            $time = $this->timer->stop($call->getRequest()->getMethod());
            $logLevel = max($logLevel, $this->timer->getLogLevel($time));
            $msg = sprintf('%s (%.2f seconds)', $msg, $time);
        }
        $logAttributes = $call->getLogAttributes();
        if ($call->isCancelled()) {
            $logAttributes['cancelled'] = implode(',', $call->getCancelledBy());
        }
        if ($call->getResponse()->isError()) {
            $fault = $call->getResponse()->getError();
            $faultLogLevel = self::ERROR;
            if ($fault instanceof LoggableException) {
                /** @var LoggableException $fault */
                $faultLogLevel = max($logLevel, $fault->getLogLevel());
            }
            $this->addLogRecord(
                max($logLevel, $faultLogLevel),
                sprintf(
                    '%s [%s "%s"]',
                    $call->getRequest()->getMethod(),
                    get_class($fault),
                    $fault->getMessage()
                ),
                $logAttributes
            );
        } else {
            $this->addLogRecord($logLevel, $msg, $logAttributes);
        }
    }


    /**
     * Formats the XML using DOMDocument
     *
     * @param string $xml
     * @return string
     */
    public static function formatXml($xml)
    {
        try {
            if (!empty($xml)) {
                $domDocument = new DOMDocument();
                $domDocument->loadXml($xml);
                $domDocument->formatOutput = true;

                return $domDocument->saveXML();
            } else {
                throw new Exception('XML was left empty');
            }
        } catch (Exception $e) {
        }
        return $xml;
    }


    /**
     * Returns the default log level for the given event.
     *
     * @param ServiceCallInterface $event
     * @return int
     */
    protected function getDefaultLogLevel(ServiceCallInterface $event)
    {
        $ret = 100;
        foreach ($this->raisedLogLevels as $logLevel => $methods) {
            if (in_array(strtolower($event->getRequest()->getMethod()), $methods)) {
                $ret = $logLevel;
                break;
            }
        }
        return $ret;
    }


    /**
     * Set some methods to be raised in log level, e.g. set a specific method to INFO
     *
     * @param array $logLevels
     * @return void
     */
    public function setRaisedLogLevels($logLevels)
    {
        foreach ($logLevels as $config) {
            if (!isset($this->raisedLogLevels[$config['level']])) {
                $this->raisedLogLevels[$config['level']] = array();
            }
            $this->raisedLogLevels[$config['level']] = array_merge(
                $this->raisedLogLevels[$config['level']],
                array_map('strtolower', $config['methods'])
            );
        }
    }
}