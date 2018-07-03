<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Bridge\DataCollector;

use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\Bridge\DataCollector\Observer;
use Zicht\Service\Common\RequestInterface;
use Zicht\Service\Common\ResponseInterface;
use Zicht\Service\Common\ServiceCallInterface;

class ObserverTest extends TestCase
{
    public function testCollection()
    {
        $call = $this->getMockBuilder(ServiceCallInterface::class)->getMock();
        $call->expects($this->any())->method('getRequest')->will($this->returnValue($req = $this->getMockBuilder(RequestInterface::class)->getMock()));
        $call->expects($this->any())->method('getResponse')->will($this->returnValue($response = $this->getMockBuilder(ResponseInterface::class)->getMock()));

        $response->expects($this->any())->method('getResponse')->will($this->returnValue('The response'));
        $response->expects($this->any())->method('isError')->will($this->returnValue(false));

        $observer = new Observer();
        $observer->notifyBefore($call);
        $observer->notifyAfter($call);

        list($call) = array_values($observer->getCalls());

        $this->assertEquals('The response', $call['response']);
        $this->assertInternalType('double', $call['t_start']);
        $this->assertInternalType('double', $call['t_end']);
        $this->assertInternalType('int', $call['mem_start']);
        $this->assertInternalType('int', $call['mem_end']);

        $this->assertFalse($call['is_error']);
    }

    /**
     * @dataProvider supportedErrors
     */
    public function testErrorData($error, $message)
    {
        $call = $this->getMockBuilder(ServiceCallInterface::class)->getMock();
        $call->expects($this->any())->method('getRequest')->will($this->returnValue($req = $this->getMockBuilder(RequestInterface::class)->getMock()));
        $call->expects($this->any())->method('getResponse')->will($this->returnValue($response = $this->getMockBuilder(ResponseInterface::class)->getMock()));

        $response->expects($this->any())->method('getResponse')->will($this->returnValue('The response'));
        $response->expects($this->any())->method('isError')->will($this->returnValue(true));
        $response->expects($this->any())->method('getError')->will($this->returnValue($error));

        $observer = new Observer();
        $observer->notifyBefore($call);
        $observer->notifyAfter($call);

        list($call) = array_values($observer->getCalls());

        $this->assertEquals('The response', $call['response']);
        $this->assertTrue($call['is_error']);
        $this->assertEquals($message, $call['error']);
    }

    public function supportedErrors()
    {
        return [
            ['The error', 'The error'],
            [new \Exception('the error'), 'the error (Exception)'],
            [new \stdClass, 'Unknown error (stdClass)'],
        ];
    }
}
