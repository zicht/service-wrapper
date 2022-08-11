<?php declare(strict_types=1);
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common;

use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\Observers\ServiceObserverAdapter;
use Zicht\Service\Common\ServiceCallInterface;
use Zicht\Service\Common\ServiceFactoryInterface;
use Zicht\Service\Common\ServiceWrapper;
use Zicht\Service\Common\Soap\SoapClient as ZichtSoapClient;

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
 * Class FooObserver
 */
class FooObserver extends ServiceObserverAdapter
{
}

/**
 * Class BarObserver
 */
class BarObserver extends ServiceObserverAdapter
{
}

/**
 * Class MooObserver
 */
class MooObserver extends ServiceObserverAdapter
{
}

/**
 * Class ServiceWrapperTest
 */
class ServiceWrapperTest extends TestCase
{
    /**
     * @dataProvider exposedMethods
     * @param mixed $method
     */
    public function testExposedMethodsForwardToSoapClient($method)
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

    public function exposedMethods()
    {
        return [
            ['getAvailableGenres'],
        ];
    }

    public function testGetWrappedServiceReturnsSoapImplementation()
    {
        $mock = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $soap = new ServiceWrapper($mock);
        $this->assertEquals($mock, $soap->getWrappedService());
    }

    public function testObserverWillBeNotifiedWithExpectedArguments()
    {
        $mock = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMockBuilder('Zicht\Service\Common\Observers\ServiceObserverAdapter')->getMock();

        $soap = new ServiceWrapper($mock);

        $observer->expects($this->once())->method('notifyBefore')->will(
            $this->returnCallback(function ($event) {
                $this->assertEquals('testMethod', $event->getRequest()->getMethod());
                $this->assertEquals(['arg1', 'arg2'], $event->getRequest()->getParameters());
            })
        );
        $observer->expects($this->once())->method('notifyAfter')->will(
            $this->returnCallback(function ($event) {
                $this->assertEquals('testMethod', $event->getRequest()->getMethod());
                $this->assertEquals(['arg1', 'arg2'], $event->getRequest()->getParameters());
                $this->assertEquals(null, $event->getResponse()->getError());
                $this->assertEquals(null, $event->getResponse()->getResponse());
            })
        );
        $soap->registerObserver($observer);
        $soap->testMethod('arg1', 'arg2');
    }

    public function testPassingParametersAreImmutable()
    {
        $this->expectException(\LogicException::class);
        $mock = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMockBuilder('Zicht\Service\Common\Observers\ServiceObserverAdapter')->getMock();

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

    public function testPassingParametersAreMutableInAlterRequest()
    {
        $mock = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMockBuilder('Zicht\Service\Common\Observers\ServiceObserverAdapter')->getMock();

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

    public function testResponseIsImmutableInNotifyAfter()
    {
        $this->markTestSkipped('This should be tested');
    }

    public function testCancellingEventWillCancelExecution()
    {
        $mock = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMockBuilder('Zicht\Service\Common\Observers\ServiceObserverAdapter')->getMock();
        $soap = new ServiceWrapper($mock, []);

        $soap->registerObserver(new CancellingObserver());
        $mock->expects($this->never())->method('testMethod');
        $soap->registerObserver($observer);
        $soap->testMethod('arg1', 'arg2');
    }

    public function testSoapFaultIsDelegatedToObserver()
    {
        $mock = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $observer = $this->getMockBuilder('Zicht\Service\Common\Observers\ServiceObserverAdapter')->getMock();

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
                            $event->getResponse()->setError(new \DomainException('a', 0, $event->getResponse()->getError()));
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

    public function testObserversAreAllNotifiedEvenIfExceptionIsThrown()
    {
        $mock = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod'])->getMock();
        $mock->method('testMethod')->will($this->returnValue('test'));

        $observer = $this->getMockBuilder('\Zicht\Service\Common\Observers\ServiceObserverAdapter')->getMock();
        $observer2 = $this->getMockBuilder('\Zicht\Service\Common\Observers\ServiceObserverAdapter')->getMock();
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

    public function testNestedCallsWillHaveEventParent()
    {
        $mock = $this->getMockBuilder(ZichtSoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod', 'resetSoapInputHeaders', 'getLastResponse', 'getLastRequest'])->getMock();
        $mock->method('testMethod')->will($this->returnValue('test'));
        $service = new ServiceWrapper($mock);

        $observer = $this->getMockBuilder('Zicht\Service\Common\Observers\ServiceObserverAdapter')->getMock();

        $calls = 0;
        $observer
            ->expects($this->exactly(2))
            ->method('notifyBefore')
            ->will(
                $this->returnCallback(
                    function ($event) use ($service, &$calls) {
                        ++$calls;
                        if ($calls == 1) {
                            $this->assertFalse($event->hasParent());
                            $service->testMethod();
                        } elseif ($calls == 2) {
                            $this->assertTrue($event->hasParent());
                            $this->assertTrue($event->getParent()->getRequest()->isMethod('testMethod'));
                            $this->assertTrue($event->getRequest()->isMethod('testMethod'));
                        }
                    }
                )
            );

        $service->registerObserver($observer);
        $service->testMethod();
        $this->assertEquals(2, $calls);
    }

    public function testObserverOrder()
    {
        $mock = $this->getMockBuilder(ZichtSoapClient::class)->disableOriginalConstructor()->setMethods(['testMethod', 'resetSoapInputHeaders', 'getLastResponse', 'getLastRequest'])->getMock();
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

    public function testRegisterObservers()
    {
        $wrapper = new ServiceWrapper(new \stdClass());
        $wrapper->registerObservers([
            $foo = $this->getMockBuilder(FooObserver::class)->getMock(),
        ]);
        $wrapper->registerObservers([
            $bar = $this->getMockBuilder(BarObserver::class)->getMock(),
            $moo = $this->getMockBuilder(MooObserver::class)->getMock(),
        ]);
        $this->assertEquals([$foo, $bar, $moo], $wrapper->getObservers());
    }
}
