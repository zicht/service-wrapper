<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common;

use Zicht\Service\Common\RequestInterface;
use Zicht\Service\Common\ResponseInterface;
use Zicht\Service\Common\ServiceCall;
use Zicht\Service\Common\ServiceWrapper;

/**
 * @covers Zicht\Service\Common\ServiceCall
 */
class ServiceCallTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->call = new ServiceCall(
            $this->service = $this->getMockBuilder(ServiceWrapper::class)->disableOriginalConstructor()->getMock(),
            $this->request = $this->getMock(RequestInterface::class),
            $this->response = $this->getMock(ResponseInterface::class),
            $this->parent = $this->getMockBuilder(ServiceCallInterface::class)->disableOriginalConstructor()->getMock()
        );
    }


    public function testConstructWithoutParent()
    {
        $call = new ServiceCall(
            $this->getMockBuilder(ServiceWrapper::class)->disableOriginalConstructor()->getMock(),
            $this->getMock(RequestInterface::class),
            $this->getMock(ResponseInterface::class)
        );
        $this->assertNull($call->getParent());
        $this->assertFalse($call->hasParent());
    }

    public function testConstruct()
    {
        $this->assertEquals($this->service, $this->call->getService());
        $this->assertEquals($this->request, $this->call->getRequest());
        $this->assertEquals($this->response, $this->call->getResponse());
        $this->assertEquals($this->parent, $this->call->getParent());
        $this->assertTrue($this->call->hasParent());
    }

    public function testCancel()
    {
        $this->call->cancel('foo');
        $this->assertTrue($this->call->isCancelled());
        $this->assertTrue($this->call->isCancelled('foo'));
        $this->assertFalse($this->call->isCancelled('bar'));

        $this->assertEquals(['foo'], $this->call->getCancelledBy());
    }

    public function testCancelByClassnameDescription()
    {
        $obj = new \stdClass();
        $this->call->cancel($obj);
        $this->assertTrue($this->call->isCancelled());
        $this->assertFalse($this->call->isCancelled('foo'));
        $this->assertFalse($this->call->isCancelled($obj));
    }


    public function testLogAttributes()
    {
        $this->call->addLogAttributes(['a' => 'b']);
        $this->assertEquals(['a' => 'b'], $this->call->getLogAttributes());

        $this->call->addLogAttributes(['x' => 'y']);
        $this->assertEquals(['a' => 'b', 'x' => 'y'], $this->call->getLogAttributes());
    }
}