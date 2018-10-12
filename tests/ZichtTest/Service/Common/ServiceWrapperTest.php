<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common;

use Zicht\Service\Common\Observers\ServiceObserverAdapter;
use Zicht\Service\Common\ServiceCallInterface;
use Zicht\Service\Common\ServiceFactoryInterface;
use Zicht\Service\Common\ServiceObserver;
use Zicht\Service\Common\ServiceWrapper;

/**
 * Class CancellingObserver
 *
 * @package ZichtTest\Service\Common
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
 *
 * @covers \Zicht\Service\Common\ServiceWrapper
 *
 * @package ZichtTest\Service\Common
 */
class ServiceWrapperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider exposedMethods
     * @return void
     */
    function testExposedMethodsForwardToSoapClient($method)
    {
        $mock = $this->getMockBuilder('\SoapClient')->setMethods([$method])->disableOriginalConstructor()->getMock();

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
        $mock = $this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $soap = new ServiceWrapper($mock, []);
        $this->assertEquals($mock, $soap->getWrappedService());
    }


    /**
     * @covers \Zicht\Service\Common\ServiceWrapper
     * @covers \Zicht\Service\Common\ServiceObserver
     */
    function testObserverWillBeNotifiedWithExpectedArguments()
    {

        $mock = $this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMock('Zicht\Service\Common\ServiceObserver');

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
        $mock = $this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMock('Zicht\Service\Common\ServiceObserver');

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
        $mock = $this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMock('Zicht\Service\Common\ServiceObserver');

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

    }


    function testCancellingEventWillCancelExecution()
    {
        $mock = $this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMock('Zicht\Service\Common\ServiceObserver');
        $soap = new ServiceWrapper($mock, []);

        $soap->registerObserver(new CancellingObserver());
        $mock->expects($this->never())->method('testMethod');
        $soap->registerObserver($observer);
        $soap->testMethod('arg1', 'arg2');
    }


    function testSoapFaultIsDelegatedToObserver()
    {
        $mock = $this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMock('Zicht\Service\Common\ServiceObserver');

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
        $mock = $this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();

        $observer = $this->getMock('\Zicht\Service\Common\ServiceObserver');
        $observer2 = $this->getMock('\Zicht\Service\Common\ServiceObserver');
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
        $mock = $this->getMock('Sro\Service\SoapClient', ['testMethod', 'resetSoapInputHeaders', 'getLastResponse', 'getLastRequest']);
        $service = new ServiceWrapper($mock);

        $observer = $this->getMock('Zicht\Service\Common\ServiceObserver');

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

        $observer = $this->getMock('Zicht\Service\Common\Observers\LoggableServiceObserverAdapter', ['setLogger']);
        $observer->expects($this->once())->method('setLogger')->with($loggerInstance);

        $service = new ServiceWrapper($this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->getMock());
        $service->setLogger($loggerInstance);
        $service->registerObserver($observer);
    }


    function testSetLoggerWillRegisterLoggerObjectWithObserversIfRegisteredBeforeSetLogger()
    {
        $loggerInstance = $this->getMockBuilder('\Monolog\Logger')->disableOriginalConstructor()->getMock();

        $observer = $this->getMock('Zicht\Service\Common\Observers\LoggableServiceObserverAdapter', ['setLogger']);
        $observer->expects($this->once())->method('setLogger')->with($loggerInstance);

        $service = new ServiceWrapper($this->getMockBuilder('\SoapClient')->disableOriginalConstructor()->getMock());

        $service->registerObserver($observer);
        $service->setLogger($loggerInstance);
    }


    function testObserverOrder()
    {
        $service = new ServiceWrapper($this->getMock('Sro\Service\SoapClient', ['method', 'resetSoapInputHeaders', 'getLastResponse', 'getLastRequest']));

        $observer1 = $this->getMock('Zicht\Service\Common\Observers\ServiceObserverAdapter');
        $observer2 = $this->getMock('Zicht\Service\Common\Observers\ServiceObserverAdapter');

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

        $service->method();
        $this->assertEquals([
            'notifyBefore@1',
            'notifyBefore@2',
            'notifyAfter@1',
            'notifyAfter@2',
        ], $order);
    }


    public function testServiceFactoryWillNotCreateServiceAtConstructionTime()
    {
        $mock = $this->getMock(ServiceFactoryInterface::class);
        $mock->expects($this->never())->method('createService');
        $wrapper = new ServiceWrapper($mock);
    }

    public function testServiceFactoryWillCreateServiceIfWrappedServiceIsRequested()
    {
        $mock = $this->getMock(ServiceFactoryInterface::class);
        $mock->expects($this->once())->method('createService');
        $wrapper = new ServiceWrapper($mock);
        $wrapper->getWrappedService();
    }

    public function testServiceFactoryWillCreateServiceOnceIfWrappedServiceIsRequestedMultipleTimes()
    {
        $mock = $this->getMock(ServiceFactoryInterface::class);
        $mock->expects($this->once())->method('createService');
        $wrapper = new ServiceWrapper($mock);
        $wrapper->getWrappedService();
        $wrapper->getWrappedService();
    }


    public function testRegisterObserverAtIndex()
    {
        $wrapper = new ServiceWrapper(new \stdClass());
        $wrapper->registerObserver($first = $this->getMock(ServiceObserver::class));
        $this->assertEquals([$first], $wrapper->getObservers());
        $wrapper->registerObserver($second = $this->getMock(ServiceObserver::class));
        $this->assertEquals([$first, $second], $wrapper->getObservers());
        $wrapper->registerObserver($newSecond = $this->getMock(ServiceObserver::class), 1);
        $this->assertEquals([$first, $newSecond, $second], $wrapper->getObservers());
    }

    public function testUnregisterObserver()
    {
        $wrapper = new ServiceWrapper(new \stdClass());
        $wrapper->registerObserver($first = $this->getMock(ServiceObserver::class));
        $this->assertEquals([$first], $wrapper->getObservers());
        $wrapper->registerObserver($second = $this->getMock(ServiceObserver::class));
        $this->assertEquals([$first, $second], $wrapper->getObservers());
        $wrapper->registerObserver($newSecond = $this->getMock(ServiceObserver::class), 1);
        $this->assertEquals([$first, $newSecond, $second], $wrapper->getObservers());

        $this->assertEquals([0, $first], $wrapper->unregisterObserver($first));
        $this->assertEquals([$newSecond, $second], $wrapper->getObservers());

        $this->assertEquals(null, $wrapper->unregisterObserver('SomeUnregisteredObserver'));
    }
}
