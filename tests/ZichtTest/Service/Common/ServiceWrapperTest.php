<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common;

use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\Observers\ServiceObserverAdapter;
use Zicht\Service\Common\ServiceCallInterface;
use Zicht\Service\Common\ServiceFactoryInterface;
use Zicht\Service\Common\ServiceObserver;
use Zicht\Service\Common\ServiceWrapper;

/**
 * Class CancellingObserver
 */
class CancellingObserver extends ServiceObserverAdapter
{
    /**
     * {@inheritdoc}
     */
    public function notifyBefore(ServiceCallInterface $message)
    {
        $message->cancel($this);
    }
}

/**
 * Class ServiceWrapperTest
 */
class ServiceWrapperTest extends TestCase
{
    /**
     * @dataProvider exposedMethods
     * @return void
     */
    function testExposedMethodsForwardToSoapClient($method)
    {
        $mock = $this->getMockBuilder(\SoapClient::class)->setMethods([$method])->disableOriginalConstructor()->getMock();

        $mock
            ->expects($this->once())
            ->method($method)
            ->will(
                $this->returnValue('w00t')
            );
        $soap = new ServiceWrapper($mock);
        $this->assertEquals('w00t', $soap->$method());
    }


    function testGetWrappedServiceReturnsSoapImplementation()
    {
        $mock = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $soap = new ServiceWrapper($mock);
        $this->assertEquals($mock, $soap->getWrappedService());
    }


    /**
     * @covers \Zicht\Service\Common\ServiceWrapper
     * @covers \Zicht\Service\Common\ServiceObserver
     */
    function testObserverWillBeNotifiedWithExpectedArguments()
    {
        $mock = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMockBuilder('Zicht\Service\Common\ServiceObserver')->getMock();

        $soap = new ServiceWrapper($mock);

        $self = $this;
        $observer->expects($this->once())->method('notifyBefore')->will(
            $this->returnCallback(function ($event) use ($self) {
                $self->assertEquals('testMethod', $event->getRequest()->getMethod());
                $self->assertEquals(['arg1', 'arg2'], $event->getRequest()->getParameters());
            })
        );
        $observer->expects($this->once())->method('notifyAfter')->will(
            $this->returnCallback(function ($event) use ($self) {
                $self->assertEquals('testMethod', $event->getRequest()->getMethod());
                $self->assertEquals(['arg1', 'arg2'], $event->getRequest()->getParameters());
                $self->assertEquals(null, $event->getResponse()->getError());
                $self->assertEquals(null, $event->getResponse()->getResponse());
            })
        );
        $soap->registerObserver($observer);
        $soap->testMethod('arg1', 'arg2');
    }


    /**
     * @expectedException \LogicException
     */
    function testPassingParametersAreImmutable()
    {
        $mock = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMockBuilder('Zicht\Service\Common\ServiceObserver')->getMock();

        $soap = new ServiceWrapper($mock, []);

        $observer
            ->expects($this->once())
            ->method('notifyBefore')
            ->will(
                $this->returnCallback(
                    function (ServiceCallInterface $event) {
                        $event->getRequest()->setParameterDeep([0], 'mutated');
                    }
                )
            );
        $soap->registerObserver($observer);
        $soap->testMethod('arg1', 'arg2');
    }


    function testPassingParametersAreMutableInAlterRequest()
    {
        $mock = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMockBuilder('Zicht\Service\Common\ServiceObserver')->getMock();

        $soap = new ServiceWrapper($mock, []);

        $observer
            ->expects($this->once())
            ->method('alterRequest')
            ->will(
                $this->returnCallback(
                    function (ServiceCallInterface $event) {
                        $event->getRequest()->setParameterDeep([0], 'mutated');
                    }
                )
            );

        $mock->expects($this->once())->method('testMethod')->with('mutated', 'arg2');
        $soap->registerObserver($observer);
        $soap->testMethod('arg1', 'arg2');
    }


    function testResponseIsImmutableInNotifyAfter()
    {
        $this->markTestSkipped('This should be tested');
    }


    function testCancellingEventWillCancelExecution()
    {
        $mock = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMockBuilder('Zicht\Service\Common\ServiceObserver')->getMock();
        $soap = new ServiceWrapper($mock, []);

        $soap->registerObserver(new CancellingObserver());
        $mock->expects($this->never())->method('testMethod');
        $soap->registerObserver($observer);
        $soap->testMethod('arg1', 'arg2');
    }


    function testSoapFaultIsDelegatedToObserver()
    {
        $mock = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMockBuilder('Zicht\Service\Common\ServiceObserver')->getMock();

        $soap = new ServiceWrapper($mock, []);

        $fault = new \SoapFault('a', 'b');
        $mock->expects($this->once())
            ->method('testMethod')
            ->withAnyParameters()
            ->will($this->throwException($fault));

        $observer
            ->expects($this->once())
            ->method('alterResponse')
            ->will(
                $this->returnCallback(
                    function (ServiceCallInterface $event) {
                        if ($event->getResponse()->isError()) {
                            $event->getResponse()->setError(new \DomainException('a', null, $event->getResponse()->getError()));
                        }
                    }
                )
            );

        $soap->registerObserver($observer);
        $ex = null;
        try {
            $soap->testMethod();
        } catch (\DomainException $ex) {
        }
        if (is_null($ex)) {
            $this->fail('ServiceException was not thrown by testMethod');
        }
    }

    function testObserversAreAllNotifiedEvenIfExceptionIsThrown()
    {
        $mock = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $mock->method('testMethod')->will($this->returnValue('test'));

        $observer = $this->getMockBuilder('\Zicht\Service\Common\ServiceObserver')->getMock();
        $observer2 = $this->getMockBuilder('\Zicht\Service\Common\ServiceObserver')->getMock();
        $observer2->expects($this->once())->method('notifyBefore');
        $observer2->expects($this->once())->method('notifyAfter');

        $soap = new ServiceWrapper($mock);

        $fault = new \SoapFault('a', 'b');
        $mock->expects($this->once())->method('testMethod')->will($this->throwException($fault));

        $observer
            ->expects($this->once())
            ->method('notifyAfter')
            ->will($this->returnValue(null));

        $soap->registerObserver($observer);
        $soap->registerObserver($observer2);

        $ex = null;
        try {
            $soap->testMethod();
        } catch (\Exception $ex) {
        }
        if (is_null($ex)) {
            $this->fail('No exception was not thrown by testMethod');
        }
    }


    function testNestedCallsWillHaveEventParent()
    {
        $mock = $this->getMockBuilder('Sro\Service\SoapClient', ['testMethod', 'resetSoapInputHeaders', 'getLastResponse', 'getLastRequest'])->getMock();
        $mock->method('testMethod')->will($this->returnValue('test'));
        $service = new ServiceWrapper($mock);

        $observer = $this->getMockBuilder('Zicht\Service\Common\ServiceObserver')->getMock();

        $calls = 0;
        $self = $this;
        $observer
            ->expects($this->exactly(2))
            ->method('notifyBefore')
            ->will(
                $this->returnCallback(
                    function ($event) use ($self, $service, &$calls) {
                        $calls++;
                        if ($calls == 1) {
                            $self->assertFalse($event->hasParent());
                            $service->testMethod();
                        } elseif ($calls == 2) {
                            $self->assertTrue($event->hasParent());
                            $self->assertTrue($event->getParent()->getRequest()->isMethod('testMethod'));
                            $self->assertTrue($event->getRequest()->isMethod('testMethod'));
                        }
                    }
                )
            );;

        $service->registerObserver($observer);
        $service->testMethod();
        $this->assertEquals(2, $calls);
    }


    function exposedMethods()
    {
        return [
            ['getAvailableGenres'],
        ];
    }


    function testSetLoggerWillRegisterLoggerObjectWithObserversIfRegisteredAfterSetLogger()
    {
        $loggerInstance = $this->getMockBuilder('\Monolog\Logger')->disableOriginalConstructor()->getMock();

        $observer = $this->getMockBuilder('Zicht\Service\Common\Observers\LoggableServiceObserverAdapter', ['setLogger'])->getMock();
        $observer->expects($this->once())->method('setLogger')->with($loggerInstance);

        $service = new ServiceWrapper($this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->getMock());
        $service->setLogger($loggerInstance);
        $service->registerObserver($observer);
    }


    function testSetLoggerWillRegisterLoggerObjectWithObserversIfRegisteredBeforeSetLogger()
    {
        $loggerInstance = $this->getMockBuilder('\Monolog\Logger')->disableOriginalConstructor()->getMock();

        $observer = $this->getMockBuilder('Zicht\Service\Common\Observers\LoggableServiceObserverAdapter', ['setLogger'])->getMock();
        $observer->expects($this->once())->method('setLogger')->with($loggerInstance);

        $service = new ServiceWrapper($this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->getMock());

        $service->registerObserver($observer);
        $service->setLogger($loggerInstance);
    }


    function testObserverOrder()
    {
        $mock = $this->getMockBuilder('Sro\Service\SoapClient', ['testMethod', 'resetSoapInputHeaders', 'getLastResponse', 'getLastRequest'])->getMock();
        $mock->method('testMethod')->will($this->returnValue('test'));
        $service = new ServiceWrapper($mock);

        $observer1 = $this->getMockBuilder('Zicht\Service\Common\Observers\ServiceObserverAdapter')->getMock();
        $observer2 = $this->getMockBuilder('Zicht\Service\Common\Observers\ServiceObserverAdapter')->getMock();

        $order = [];
        $observer1->expects($this->once())->method('notifyBefore')->will($this->returnCallback(
            function () use (&$order) {
                $order[] = 'notifyBefore@1';
            }
        ));
        $observer2->expects($this->once())->method('notifyBefore')->will($this->returnCallback(
            function () use (&$order) {
                $order[] = 'notifyBefore@2';
            }
        ));

        $observer1->expects($this->once())->method('notifyAfter')->will($this->returnCallback(
            function () use (&$order) {
                $order[] = 'notifyAfter@1';
            }
        ));
        $observer2->expects($this->once())->method('notifyAfter')->will($this->returnCallback(
            function () use (&$order) {
                $order[] = 'notifyAfter@2';
            }
        ));

        $service->registerObserver($observer1);
        $service->registerObserver($observer2);

        $service->testMethod();
        $this->assertEquals([
            'notifyBefore@1',
            'notifyBefore@2',
            'notifyAfter@1',
            'notifyAfter@2',
        ], $order);
    }


    public function testServiceFactoryWillNotCreateServiceAtConstructionTime()
    {
        $mock = $this->getMockBuilder(ServiceFactoryInterface::class)->getMock();
        $mock->expects($this->never())->method('createService');
        $wrapper = new ServiceWrapper($mock);
    }

    public function testServiceFactoryWillCreateServiceIfWrappedServiceIsRequested()
    {
        $mock = $this->getMockBuilder(ServiceFactoryInterface::class)->getMock();
        $mock->expects($this->once())->method('createService');
        $wrapper = new ServiceWrapper($mock);
        $wrapper->getWrappedService();
    }

    public function testServiceFactoryWillCreateServiceOnceIfWrappedServiceIsRequestedMultipleTimes()
    {
        $mock = $this->getMockBuilder(ServiceFactoryInterface::class)->getMock();
        $mock->expects($this->once())->method('createService');
        $wrapper = new ServiceWrapper($mock);
        $wrapper->getWrappedService();
        $wrapper->getWrappedService();
    }


    public function testRegisterObserverAtIndex()
    {
        $wrapper = new ServiceWrapper(new \stdClass());
        $wrapper->registerObserver($first = $this->getMockBuilder(ServiceObserver::class)->getMock());
        $this->assertEquals([$first], $wrapper->getObservers());
        $wrapper->registerObserver($second = $this->getMockBuilder(ServiceObserver::class)->getMock());
        $this->assertEquals([$first, $second], $wrapper->getObservers());
        $wrapper->registerObserver($newSecond = $this->getMockBuilder(ServiceObserver::class)->getMock(), 1);
        $this->assertEquals([$first, $newSecond, $second], $wrapper->getObservers());
    }

    public function testUnregisterObserver()
    {
        $wrapper = new ServiceWrapper(new \stdClass());
        $wrapper->registerObserver($first = $this->getMockBuilder(ServiceObserver::class)->getMock());
        $this->assertEquals([$first], $wrapper->getObservers());
        $wrapper->registerObserver($second = $this->getMockBuilder(ServiceObserver::class)->getMock());
        $this->assertEquals([$first, $second], $wrapper->getObservers());
        $wrapper->registerObserver($newSecond = $this->getMockBuilder(ServiceObserver::class)->getMock(), 1);
        $this->assertEquals([$first, $newSecond, $second], $wrapper->getObservers());

        $this->assertEquals([0, $first], $wrapper->unregisterObserver($first));
        $this->assertEquals([$newSecond, $second], $wrapper->getObservers());

        $this->assertEquals(null, $wrapper->unregisterObserver('SomeUnregisteredObserver'));
    }
}
