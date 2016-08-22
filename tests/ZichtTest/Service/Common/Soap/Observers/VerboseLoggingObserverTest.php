<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Soap\Observers;

use Zicht\Service\Common\ServiceCallInterface;
use Zicht\Service\Common\ServiceWrapper;
use Zicht\Service\Common\Soap\Observer\VerboseLoggingObserver;

class VerboseLoggingObserverTest extends \PHPUnit_Framework_TestCase
{
    public function testRequestXmlIsAdded()
    {
        $soap = $this->getMockBuilder(\SoapClient::class)->disableOriginalConstructor()->getMock();
        $soap->expects($this->once())->method('__getLastRequest')->will($this->returnValue('request body'));
        $soap->expects($this->once())->method('__getLastResponse')->will($this->returnValue('response body'));
        $wrapper = $this->getMockBuilder(ServiceWrapper::class)->disableOriginalConstructor()->getMock();
        $wrapper->expects($this->once())->method('getWrappedService')->will($this->returnValue($soap));
        $call = $this->getMock(ServiceCallInterface::class);
        $call->expects($this->once())->method('getService')->will($this->returnValue($wrapper));
        $call->expects($this->once())->method('addLogAttributes')->with(
            [
                'request' => 'request body',
                'response' => 'response body'
            ]
        );

        $observer = new VerboseLoggingObserver();
        $observer->notifyAfter($call);
    }
}