<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace SroTest\Service;

use PHPUnit_Framework_TestCase,
    Sro\Service\SoapClient,
    \Sro\Service\SoapWrapper,
    \SoapFault,
    \SoapHeader,
    \Sro\Service\ServiceObserver,
    \Sro\Service\ServiceException;


class CancellingObserver extends \Sro\Service\Observers\ServiceObserverAdapter {
    function notifyBefore(\Sro\Service\Observable $message) {
        $message->cancel($this);
    }

}

/**
 * @covers \Sro\Service\SoapWrapper
 */
class SoapWrapperTest extends PHPUnit_Framework_TestCase {
    /**
     * @dataProvider exposedMethods
     * @return void
     */
    function testExposedMethodsForwardToSoapClient($method) {
        $mock = $this->getMock('Sro\Service\SoapClient', array($method, 'resetSoapInputHeaders', 'getLastRequest', 'getLastResponse'));

        $mock
                ->expects($this->once())
                ->method($method)
                ->will(
            $this->returnValue('w00t')
        );
        $soap = new SoapWrapper($mock, array());
        $this->assertEquals('w00t', $soap->$method());
    }


    function testGetInnerServiceReturnsSoapImplementation() {
        $mock = $this->getMock('Sro\Service\SoapClient');
        $soap = new SoapWrapper($mock, array());
        $this->assertEquals($mock, $soap->getInnerService());
    }





    function testGetEntityManagerWillReturnEntityManager() {
        $soap = new SoapWrapper($this->getMock('Sro\Service\SoapClient'), array());
        $em = $soap->getEntityManager('MyClassName');
        $this->assertInstanceOf('Sro\Service\EntityManager', $em);
        $this->assertEquals('MyClassName', $em->getType());
    }


    function testGetEntityManagerWillNotReinstantiateIfAlreadyRequested() {
        $soap = new SoapWrapper($this->getMock('Sro\Service\SoapClient'), array());
        $em1 = $soap->getEntityManager('MyClassName');
        $em2 = $soap->getEntityManager('MyClassName');
        $this->assertEquals(spl_object_hash($em1), spl_object_hash($em2));
    }


    function testConnectWithoutContextIdWillRequestNewContextId() {
        $mock = $this->getMock('Sro\Service\SoapClient', array('Connect', 'resetSoapInputHeaders', 'getLastRequest', 'getLastResponse'));

        // use a random context id
        $mock
                ->expects($this->any())
                ->method('Connect')
                ->will(
            $this->returnValue(
                (object)array(
                    'ContextId' => 'test' . rand(0, 999)
                )
            )
        );
        $soap = new SoapWrapper($mock, array());
        $soap->connect(null);
    }


    /**
     * @covers \Sro\Service\SoapWrapper
     * @covers \Sro\Service\ServiceObserver
     */
    function testObserverWillBeNotifiedWithExpectedArguments() {
        $mock = $this->getMock('Sro\Service\SoapClient', array('testMethod', 'resetSoapInputHeaders', 'getLastRequest', 'getLastResponse'));
        $observer = $this->getMock('\Sro\Service\ServiceObserver', array('notifyBefore', 'notifyAfter'));

        $soap = new SoapWrapper($mock, array());

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
                $self->assertEquals(null, $event->getResponse()->getFault());
                $self->assertEquals(null, $event->getResponse()->getResponse());
            })
        );
        $soap->registerObserver($observer);
        $soap->testMethod('arg1', 'arg2');
    }


    function testPassingParametersAreMutable() {
        $mock = $this->getMock('Sro\Service\SoapClient', array('testMethod', 'resetSoapInputHeaders', 'getLastResponse', 'getLastRequest'));
        $observer = $this->getMock('\Sro\Service\ServiceObserver', array('notifyBefore', 'notifyAfter'));

        $soap = new SoapWrapper($mock, array());

        $observer
                ->expects($this->once())
                ->method('notifyBefore')
                ->will(
            $this->returnCallback(
                function(\Sro\Service\Observable $event) {
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
        $observer = $this->getMock('\Sro\Service\ServiceObserver', array('notifyBefore', 'notifyAfter'));

        $soap = new SoapWrapper($mock, array());

        $soap->registerObserver(new CancellingObserver());
        $mock->expects($this->never())->method('testMethod');
        $soap->registerObserver($observer);
        $soap->testMethod('arg1', 'arg2');
    }

    function testSoapFaultIsDelegatedToObserver() {
        $mock = $this->getMock('Sro\Service\SoapClient', array('testMethod', 'resetSoapInputHeaders', 'getLastResponse', 'getLastRequest'));
        $observer = $this->getMock('\Sro\Service\ServiceObserver', array('notifyBefore', 'notifyAfter'));

        $soap = new SoapWrapper($mock, array());

        $fault = new SoapFault('a', 'b');
        $mock->expects($this->once())
            ->method('testMethod')
            ->withAnyParameters()
            ->will($this->throwException($fault));

        $observer
                ->expects($this->once())
                ->method('notifyAfter')
                ->will(
                    $this->returnCallback(
                        function(\Sro\Service\Observable $event) {
                            if($event->getResponse()->isError()) {
                                $event->getResponse()->setError(new ServiceException('a', $event->getResponse()->getFault()));
                            }
                        }
                    )
                );

        $soap->registerObserver($observer);
        $ex = null;
        try {
            $soap->testMethod();
        } catch(ServiceException $ex) {
        }
        if(is_null($ex)) {
            $this->fail('ServiceException was not thrown by testMethod');
        }
    }

    function testObserversAreAllNotifiedEvenIfExceptionIsThrown() {
        $mock = $this->getMock('Sro\Service\SoapClient', array('testMethod', 'resetSoapInputHeaders', 'getLastResponse', 'getLastRequest'));
        $observer = $this->getMock('\Sro\Service\ServiceObserver', array('notifyBefore', 'notifyAfter'));
        $observer2 = $this->getMock('\Sro\Service\ServiceObserver', array('notifyBefore', 'notifyAfter'));
        $observer2->expects($this->once())->method('notifyBefore');
        $observer2->expects($this->once())->method('notifyAfter');

        $soap = new SoapWrapper($mock, array());

        $fault = new SoapFault('a', 'b');
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
        $service = new SoapWrapper($mock);

        $observer = $this->getMock('\Sro\Service\ServiceObserver', array('notifyBefore', 'notifyAfter'));

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


    function testRequestHeadersWillBeAttachedToSoapImplementation() {
        $header = new SoapHeader('http://example.org/', 'a', 'b');
        $mock = $this->getMock('Sro\Service\SoapClient', array('foo', 'resetSoapInputHeaders', 'addSoapInputHeader', 'getLastResponse', 'getLastRequest'));
        $mock->expects($this->once())->method('resetSoapInputHeaders');
        $mock->expects($this->once())->method('addSoapInputHeader')->with($header);
        $observer = $this->getMock('\Sro\Service\Observers\ServiceObserverAdapter', array('notifyBefore'));
        $observer->expects($this->once())->method('notifyBefore')->will($this->returnCallback(function($event) use($header) {
            $event->getRequest()->addHeader($header);
        }));
        $soap = new SoapWrapper($mock, array());
        $soap->registerObserver($observer);
        $soap->foo();
    }


    function testSetLoggerWillRegisterLoggerObjectWithObserversIfRegisteredAfterSetLogger() {
        $loggerInstance = $this->getMockBuilder('\Monolog\Logger')->disableOriginalConstructor()->getMock();
        $observer = $this->getMock('\Sro\Service\Observers\ServiceObserverAdapter');
        $observer->expects($this->once())->method('setLogger')->with($loggerInstance);

        $service = new SoapWrapper($this->getMock('Sro\Service\SoapClient'));

        $service->setLogger($loggerInstance);
        $service->registerObserver($observer);
    }


    function testSetLoggerWillRegisterLoggerObjectWithObserversIfRegisteredBeforeSetLogger() {
        $loggerInstance = $this->getMockBuilder('\Monolog\Logger')->disableOriginalConstructor()->getMock();
        $observer = $this->getMock('\Sro\Service\Observers\ServiceObserverAdapter');
        $observer->expects($this->once())->method('setLogger')->with($loggerInstance);

        $service = new SoapWrapper($this->getMock('Sro\Service\SoapClient'));

        $service->registerObserver($observer);
        $service->setLogger($loggerInstance);
    }


    function testObserverOrder() {
        $service = new SoapWrapper($this->getMock('Sro\Service\SoapClient', array('method', 'resetSoapInputHeaders', 'getLastResponse', 'getLastRequest')));

        $observer1 = $this->getMock('\Sro\Service\Observers\ServiceObserverAdapter');
        $observer2 = $this->getMock('\Sro\Service\Observers\ServiceObserverAdapter');

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



    function testGetEntityManager() {

    }
}