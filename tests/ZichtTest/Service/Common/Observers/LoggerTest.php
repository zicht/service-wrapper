<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common\Observers;

use \PHPUnit_Framework_TestCase;
use \Monolog\Logger as Monolog;

use Zicht\Service\Common\LoggerConstants;
use \Zicht\Service\Common\Observers\Logger as LoggerObserver;
use \Zicht\Service\Common\Observers\LoggableException;
use \Zicht\Service\Common\Response;
use \Zicht\Service\Common\Request;
use \Zicht\Service\Common\ServiceCall;

class LoggableExceptionStub extends \SoapFault implements LoggableException {
    function __construct($level) {
        parent::__construct('a', 'b');
        $this->level = $level;
    }

    function getLogLevel() {
        return $this->level;
    }
}

class LoggerTest extends PHPUnit_Framework_TestCase {
    protected $loggerImpl;
    protected $soapImpl;


    function setUp() {
        $this->service = $this->getMockBuilder('Zicht\Service\Common\ServiceWrapper')->disableOriginalConstructor()->getMock();
        $this->loggerImpl = $this->getMock('\Monolog\Logger', array('addDebug', 'addInfo', 'addCritical', 'addError', 'addWarning', 'addRecord'), array());
        $this->soapImpl = $this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->getMock();
    }

    function testFaultNotificationWillLogError() {
        $logger = new LoggerObserver($this->soapImpl);
        $logger->setLogger($this->loggerImpl);
        $recordedLevel = -1;
        $this->loggerImpl->expects($this->once())->method('addRecord')->will($this->returnCallback(function($level) use (&$recordedLevel) {
            $recordedLevel = $level;
        }));
        $response = null;
        $logger->notifyAfter(new ServiceCall($this->service, new Request(''), new Response(null, new \SoapFault('a', 'b'))));
        $this->assertGreaterThanOrEqual(LoggerConstants::ERROR, $recordedLevel);
    }

    function testCancelIsLoggedAsDebug() {
        $logger = new LoggerObserver($this->soapImpl);
        $logger->setLogger($this->loggerImpl);
        $this->loggerImpl->expects($this->once())->method('addRecord')->with(LoggerConstants::ERROR);
        $event = new ServiceCall($this->service, new Request(''), new Response(null, new \SoapFault('a', 'b')));
        $event->cancel(__METHOD__);
        $logger->notifyAfter($event);
    }


    function testLoggableExceptionWillDefineLogLevel() {
        $logger = new LoggerObserver($this->soapImpl);
        $logger->setLogger($this->loggerImpl);
        $logLevel = rand(100, 9999);
        $this->loggerImpl->expects($this->once())->method('addRecord')->with($logLevel);
        $event = new ServiceCall($this->service, new Request(''), new Response(null, new LoggableExceptionStub($logLevel)));
        $logger->notifyAfter($event);
    }
}