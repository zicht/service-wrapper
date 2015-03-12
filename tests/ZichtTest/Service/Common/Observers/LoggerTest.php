<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace SroTest\Service\Observers;
use PHPUnit_Framework_TestCase;
use Monolog\Logger as Monolog;
use \Sro\Service\Observers\Logger as LoggerObserver;
use \Sro\Service\Request;
use \Sro\Service\Response;
use \Sro\Service\Event;

class LoggableExceptionStub extends \SoapFault implements \Sro\Service\LoggableException {
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
        $this->loggerImpl = $this->getMock('\Monolog\Logger', array('addDebug', 'addInfo', 'addCritical', 'addError', 'addWarning', 'addRecord'), array('test'));
        $this->soapImpl = $this->getMock('\Sro\Service\SoapClient', array('getLastRequest', 'getLastResponse'));
    }



    function testFaultNotificationWillLogError() {
        $logger = new LoggerObserver($this->soapImpl);
        $logger->setLogger($this->loggerImpl);
        $recordedLevel = -1;
        $this->loggerImpl->expects($this->once())->method('addRecord')->will($this->returnCallback(function($level) use (&$recordedLevel) {
            $recordedLevel = $level;
        }));
        $response = null;
        $logger->notifyAfter(new Event(new Request(''), new Response(null, new \SoapFault('a', 'b'))));
        $this->assertGreaterThanOrEqual(\Monolog\Logger::ERROR, $recordedLevel);
    }


    function testCancelIsLoggedAsDebug() {
        $logger = new LoggerObserver($this->soapImpl);
        $logger->setLogger($this->loggerImpl);
        $this->loggerImpl->expects($this->once())->method('addRecord')->with(\Monolog\Logger::ERROR);
        $event = new Event(new Request(''), new Response(null, new \SoapFault('a', 'b')));
        $event->cancel(__METHOD__);
        $logger->notifyAfter($event);
    }


    function testLoggableExceptionWillDefineLogLevel() {
        $logger = new LoggerObserver($this->soapImpl);
        $logger->setLogger($this->loggerImpl);
        $logLevel = rand(1, 9999);
        $this->loggerImpl->expects($this->once())->method('addRecord')->with($logLevel);
        $event = new Event(new Request(''), new Response(null, new LoggableExceptionStub($logLevel)));
        $logger->notifyAfter($event);
    }


    function testRequestAndResponseXmlIsLoggedOnError() {
        $logger = new LoggerObserver($this->soapImpl);
        $logger->setLogger($this->loggerImpl);
        $msg = null;
        $context = array();
        $i = 0;
        $this->loggerImpl->expects($this->exactly(1))->method('addRecord')->will(
            $this->returnCallback(function($level, $m, $c) use (&$msg, &$i, &$context) {
                $context[$i] = $c;
                $msg[$i++] = $m;
            }
        ));

        $fault = new \SoapFault('a', 'b');
        $event = new Event(new Request(''), new Response(null, $fault));
        $event->getRequest()->setRequestXml('<xml><a /></xml>');
        $event->getResponse()->setResponseXml('<xml><b /></xml>');

        $logger->notifyAfter($event);
        list($request, $response) = array($context[0]['REQUEST'], $context[0]['RESPONSE']);

        $formattedXml = <<<EOXML
<xml>
  <a/>
</xml>
EOXML;
        $this->assertTrue(strpos($request, $formattedXml) !== false, 'Asserting that ' . $request . ' contains ' . $formattedXml);
        $formattedXml = <<<EOXML
<xml>
  <b/>
</xml>
EOXML;
        $this->assertTrue(strpos($response, $formattedXml) !== false, 'Asserting that ' . $response . ' contains ' . $formattedXml);
    }
}