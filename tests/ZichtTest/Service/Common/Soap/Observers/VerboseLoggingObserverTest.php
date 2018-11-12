<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Soap\Observers;

use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\ServiceCallInterface;
use Zicht\Service\Common\ServiceWrapper;
use Zicht\Service\Common\Soap\Observer\VerboseLoggingObserver;

class VerboseLoggingObserverTest extends TestCase
{
    public function testRequestXmlIsAdded()
    {
        $soap = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->getMock();
        $soap->expects($this->once())->method('__getLastRequest')->will($this->returnValue('request body'));
        $soap->expects($this->once())->method('__getLastResponse')->will($this->returnValue('response body'));
        $wrapper = $this->getMockBuilder(ServiceWrapper::class)->disableOriginalConstructor()->getMock();
        $wrapper->expects($this->once())->method('getWrappedService')->will($this->returnValue($soap));
        $call = $this->getMockBuilder(ServiceCallInterface::class)->getMock();
        $call->expects($this->once())->method('getService')->will($this->returnValue($wrapper));
        $call->expects($this->once())->method('addLogAttributes')->with(
            [
                'request' => 'request body',
                'response' => 'response body',
            ]
        );

        $observer = new VerboseLoggingObserver();
        $observer->notifyAfter($call);
    }
}
