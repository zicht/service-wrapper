<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common\Observers;

use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\LoggerConstants;
use Zicht\Service\Common\Observers\Logger as LoggerObserver;
use Zicht\Service\Common\Observers\LoggableException;
use Zicht\Service\Common\Observers\Timer;
use Zicht\Service\Common\RequestInterface;
use Zicht\Service\Common\Response;
use Zicht\Service\Common\Request;
use Zicht\Service\Common\ResponseInterface;
use Zicht\Service\Common\ServiceCall;
use Zicht\Service\Common\ServiceCallInterface;

class LoggableExceptionStub extends \SoapFault implements LoggableException
{
    function __construct($level)
    {
        parent::__construct('a', 'b');
        $this->level = $level;
    }

    function getLogLevel()
    {
        return $this->level;
    }
}

/**
 * @covers Zicht\Service\Common\Observers\Logger
 */
class LoggerTest extends TestCase
{
    protected $loggerImpl;
    protected $soapImpl;


    function setUp()
    {
        $this->service = $this->getMockBuilder('Zicht\Service\Common\ServiceWrapper')->disableOriginalConstructor()->getMock();
        $this->loggerImpl = $this->getMockBuilder('\Monolog\Logger', ['addDebug', 'addInfo', 'addCritical', 'addError', 'addWarning', 'addRecord'], [])->getMock();
        $this->soapImpl = $this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->getMock();
    }

    function testFaultNotificationWillLogError()
    {
        $logger = new LoggerObserver();
        $logger->setLogger($this->loggerImpl);
        $recordedLevel = -1;
        $this->loggerImpl->expects($this->once())->method('addRecord')->will($this->returnCallback(function ($level) use (&$recordedLevel) {
            $recordedLevel = $level;
        }));
        $response = null;
        $logger->notifyAfter(new ServiceCall($this->service, new Request(''), new Response(null, new \SoapFault('a', 'b'))));
        $this->assertGreaterThanOrEqual(LoggerConstants::ERROR, $recordedLevel);
    }

    function testCancelIsLoggedAsDebug()
    {
        $logger = new LoggerObserver();
        $logger->setLogger($this->loggerImpl);
        $this->loggerImpl->expects($this->once())->method('addRecord')->with(LoggerConstants::ERROR);
        $event = new ServiceCall($this->service, new Request(''), new Response(null, new \SoapFault('a', 'b')));
        $event->cancel(__METHOD__);
        $logger->notifyAfter($event);
    }


    function testLoggableExceptionWillDefineLogLevel()
    {
        $logger = new LoggerObserver();
        $logger->setLogger($this->loggerImpl);
        $logLevel = rand(100, 9999);
        $this->loggerImpl->expects($this->once())->method('addRecord')->with($logLevel);
        $event = new ServiceCall($this->service, new Request(''), new Response(null, new LoggableExceptionStub($logLevel)));
        $logger->notifyAfter($event);
    }


    public function testTimerIsNotifiedOfStartIfSet()
    {
        $timer = $this->getMockBuilder(Timer::class)->getMock();
        $request = new Request('methodName');

        $timer->expects($this->once())->method('start')->with('methodName');

        $call = $this->getMockBuilder(ServiceCallInterface::class)->getMock();
        $call->expects($this->any())->method('getRequest')->will($this->returnValue($request));

        $logger = new LoggerObserver($timer);
        $logger->setLogger($this->loggerImpl);
        $logger->notifyBefore($call);
    }


    /**
     *
     */
    public function testTimerIsNotNotifiedIfLoggerNotSet()
    {
        $timer = $this->getMockBuilder(Timer::class)->getMock();
        $request = new Request('methodName');

        $timer->expects($this->never())->method('start');

        $call = $this->getMockBuilder(ServiceCallInterface::class)->getMock();
        $call->expects($this->never())->method('getRequest')->will($this->returnValue($request));

        $logger = new LoggerObserver($timer);
        $logger->notifyBefore($call);
    }

    public function testBcForBooleanCtorArgs()
    {
        $refl = new \ReflectionProperty(LoggerObserver::class, 'timer');
        $refl->setAccessible(true);

        $this->assertInstanceOf(Timer::class, $refl->getValue(new LoggerObserver(true)));
        $this->assertNotInstanceOf(Timer::class, $refl->getValue(new LoggerObserver(false)));
    }


    public function testSetRaisedLogLevels()
    {
        $this->loggerImpl->expects($this->once())->method('addRecord')->with(200);
        $o = new LoggerObserver();
        $o->setRaisedLogLevels([['level' => 200, 'methods' => ['foo']]]);
        $o->setLogger($this->loggerImpl);

        $request = $this->getMockBuilder(RequestInterface::class)->getMock();
        $request->expects($this->any())->method('getMethod')->will($this->returnValue('foo'));
        $response = $this->getMockBuilder(ResponseInterface::class)->getMock();
        $call1 = $this->getMockBuilder(ServiceCallInterface::class)->getMock();
        $call1->expects($this->any())->method('getLogAttributes')->will($this->returnValue([]));
        $call1->expects($this->any())->method('getRequest')->will($this->returnValue($request));
        $call1->expects($this->any())->method('getResponse')->will($this->returnValue($response));

        $o->notifyAfter($call1);
    }
}
