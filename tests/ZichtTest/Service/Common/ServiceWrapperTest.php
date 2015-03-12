<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common;

use \Zicht\Service\Common\Observers\ServiceObserverAdapter;
use \Zicht\Service\Common\ServiceCallInterface;
use \Zicht\Service\Common\ServiceWrapper;


class CancellingObserver extends ServiceObserverAdapter {
    function notifyBefore(ServiceCallInterface $message) {
        $message->cancel($this);
    }
}

/**
 * @covers Zicht\Service\Common\ServiceWrapper
 */
class ServiceWrapperTest extends \PHPUnit_Framework_TestCase {
    /**
     * @dataProvider exposedMethods
     * @return void
     */
    function testExposedMethodsForwardToSoapClient($method) {
        $mock = $this->getMockBuilder('\SoapClient')->setMethods(array($method))->disableOriginalConstructor()->getMock();

        $mock
                ->expects($this->once())
                ->method($method)
                ->will(
            $this->returnValue('w00t')
        );
        $soap = new ServiceWrapper($mock);
        $this->assertEquals('w00t', $soap->$method());
    }


    function testGetWrappedServiceReturnsSoapImplementation() {
        $mock = $this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->setMethods(array('testMethod'))->getMock();
        $soap = new ServiceWrapper($mock, array());
        $this->assertEquals($mock, $soap->getWrappedService());
    }


    /**
     * @covers Zicht\Service\Common\ServiceWrapper
     * @covers Zicht\Service\Common\ServiceObserver
     */
    function testObserverWillBeNotifiedWithExpectedArguments() {

        $mock = $this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->setMethods(array('testMethod'))->getMock();
        $observer = $this->getMock('Zicht\Service\Common\ServiceObserver', array('notifyBefore', 'notifyAfter'));

        $soap = new ServiceWrapper($mock);

        $self = $this;
        $observer->expects($this->once())->method('notifyBefore')->will(
            $this->returnCallback(function($event) use($self){
                $self->assertEquals('testMethod', $event->getRequest()->getMethod());
                $self->assertEquals(array('arg1', 'arg2'), $event->getRequest()->getParameters());
                })
        );
        $observer->expects($this->once())->method('notifyAfter')->will(
            $this->returnCallback(function($event) use($self){
                $self->assertEquals('testMethod', $event->getRequest()->getMethod());
                $self->assertEquals(array('arg1', 'arg2'), $event->getRequest()->getParameters());
                $self->assertEquals(null, $event->getResponse()->getError());
                $self->assertEquals(null, $event->getResponse()->getResponse());
            })
        );
        $soap->registerObserver($observer);
        $soap->testMethod('arg1', 'arg2');
    }


    function testPassingParametersAreMutable() {
        $mock = $this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->setMethods(array('testMethod'))->getMock();
        $observer = $this->getMock('Zicht\Service\Common\ServiceObserver');

        $soap = new ServiceWrapper($mock, array());

        $observer
                ->expects($this->once())
                ->method('notifyBefore')
                ->will(
            $this->returnCallback(
                function(ServiceCallInterface $event) {
                    $event->getRequest()->setParameterDeep(array(0), 'mutated');
                }
            )
        );

        $mock->expects($this->once())->method('testMethod')->with('mutated', 'arg2');
        $soap->registerObserver($observer);
        $soap->testMethod('arg1', 'arg2');
    }


    function testCancellingEventWillCancelExecution() {
        $mock = $this->getMock('Sro\Service\SoapClient', array('testMethod', 'resetSoapInputHeaders', 'getLastResponse', 'getLastRequest'));
        $observer = $this->getMock('Zicht\Service\Common\ServiceObserver', array('notifyBefore', 'notifyAfter'));

        $soap = new ServiceWrapper($mock, array());

        $soap->registerObserver(new CancellingObserver());
        $mock->expects($this->never())->method('testMethod');
        $soap->registerObserver($observer);
        $soap->testMethod('arg1', 'arg2');
    }

    function testSoapFaultIsDelegatedToObserver() {
        $mock = $this->getMock('Sro\Service\SoapClient', array('testMethod', 'resetSoapInputHeaders', 'getLastResponse', 'getLastRequest'));
        $observer = $this->getMock('Zicht\Service\Common\ServiceObserver', array('notifyBefore', 'notifyAfter'));

        $soap = new ServiceWrapper($mock, array());

        $fault = new \SoapFault('a', 'b');
        $mock->expects($this->once())
            ->method('testMethod')
            ->withAnyParameters()
            ->will($this->throwException($fault));

        $observer
                ->expects($this->once())
                ->method('notifyAfter')
                ->will(
                    $this->returnCallback(
                        function(ServiceCallInterface $event) {
                            if($event->getResponse()->isError()) {
                                $event->getResponse()->setError(new \DomainException('a', null, $event->getResponse()->getError()));
                            }
                        }
                    )
                );

        $soap->registerObserver($observer);
        $ex = null;
        try {
            $soap->testMethod();
        } catch(\DomainException $ex) {
        }
        if(is_null($ex)) {
            $this->fail('ServiceException was not thrown by testMethod');
        }
    }

    function testObserversAreAllNotifiedEvenIfExceptionIsThrown() {
        $mock = $this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->setMethods(array('testMethod'))->getMock();

        $observer = $this->getMock('\Zicht\Service\Common\ServiceObserver', array('notifyBefore', 'notifyAfter'));
        $observer2 = $this->getMock('\Zicht\Service\Common\ServiceObserver', array('notifyBefore', 'notifyAfter'));
        $observer2->expects($this->once())->method('notifyBefore');
        $observer2->expects($this->once())->method('notifyAfter');

        $soap = new ServiceWrapper($mock);

        $fault = new \SoapFault('a', 'b');
        $mock->expects($this->once())->method('testMethod')->will($this->throwException($fault));

        $observer
                ->expects($this->once())
                ->method('notifyAfter')
                ->will($this->returnValue(null))
        ;

        $soap->registerObserver($observer);
        $soap->registerObserver($observer2);

        $ex = null;
        try {
            $soap->testMethod();
        } catch(\Exception $ex) {
        }
        if(is_null($ex)) {
            $this->fail('No exception was not thrown by testMethod');
        }
    }


    function testNestedCallsWillHaveEventParent() {
        $mock = $this->getMock('Sro\Service\SoapClient', array('testMethod', 'resetSoapInputHeaders', 'getLastResponse', 'getLastRequest'));
        $service = new ServiceWrapper($mock);

        $observer = $this->getMock('Zicht\Service\Common\ServiceObserver');

        $calls = 0;
        $self = $this;
        $observer
                ->expects($this->exactly(2))
                ->method('notifyBefore')
                ->will(
                    $this->returnCallback(
                        function($event) use($self, $service, &$calls) {
                            $calls++;
                            if($calls == 1) {
                                $self->assertFalse($event->hasParent());
                                $service->testMethod();
                            } elseif($calls == 2) {
                                $self->assertTrue($event->hasParent());
                                $self->assertTrue($event->getParent()->getRequest()->isMethod('testMethod'));
                                $self->assertTrue($event->getRequest()->isMethod('testMethod'));
                            }
                        }
                    )
                );
        ;

        $service->registerObserver($observer);
        $service->testMethod();
        $this->assertEquals(2, $calls);
    }


    function exposedMethods() {
        return array(
            array('getAvailableGenres'),
        );
    }


    function testSetLoggerWillRegisterLoggerObjectWithObserversIfRegisteredAfterSetLogger() {
        $loggerInstance = $this->getMockBuilder('\Monolog\Logger')->disableOriginalConstructor()->getMock();

        $observer = $this->getMock('Zicht\Service\Common\Observers\LoggableServiceObserverAdapter', array('setLogger'));
        $observer->expects($this->once())->method('setLogger')->with($loggerInstance);

        $service = new ServiceWrapper($this->getMock('Sro\Service\SoapClient'));
        $service->setLogger($loggerInstance);
        $service->registerObserver($observer);
    }


    function testSetLoggerWillRegisterLoggerObjectWithObserversIfRegisteredBeforeSetLogger() {
        $loggerInstance = $this->getMockBuilder('\Monolog\Logger')->disableOriginalConstructor()->getMock();

        $observer = $this->getMock('Zicht\Service\Common\Observers\LoggableServiceObserverAdapter', array('setLogger'));
        $observer->expects($this->once())->method('setLogger')->with($loggerInstance);

        $service = new ServiceWrapper($this->getMock('Sro\Service\SoapClient'));

        $service->registerObserver($observer);
        $service->setLogger($loggerInstance);
    }


    function testObserverOrder() {
        $service = new ServiceWrapper($this->getMock('Sro\Service\SoapClient', array('method', 'resetSoapInputHeaders', 'getLastResponse', 'getLastRequest')));

        $observer1 = $this->getMock('Zicht\Service\Common\Observers\ServiceObserverAdapter');
        $observer2 = $this->getMock('Zicht\Service\Common\Observers\ServiceObserverAdapter');

        $order = array();
        $observer1->expects($this->once())->method('notifyBefore')->will($this->returnCallback(
            function() use(&$order){
                $order[]= 'notifyBefore@1';
            }
        ));
        $observer2->expects($this->once())->method('notifyBefore')->will($this->returnCallback(
            function() use(&$order){
                $order[]= 'notifyBefore@2';
            }
        ));

        $observer1->expects($this->once())->method('notifyAfter')->will($this->returnCallback(
            function() use(&$order){
                $order[]= 'notifyAfter@1';
            }
        ));
        $observer2->expects($this->once())->method('notifyAfter')->will($this->returnCallback(
            function() use(&$order){
                $order[]= 'notifyAfter@2';
            }
        ));

        $service->registerObserver($observer1);
        $service->registerObserver($observer2);

        $service->method();
        $this->assertEquals(array(
            'notifyBefore@2',
            'notifyBefore@1',
            'notifyAfter@1',
            'notifyAfter@2'
        ), $order);
    }
}