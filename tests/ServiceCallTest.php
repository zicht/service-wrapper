<?php declare(strict_types=1);
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common;

use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\RequestInterface;
use Zicht\Service\Common\ResponseInterface;
use Zicht\Service\Common\ServiceCall;
use Zicht\Service\Common\ServiceWrapper;

class ServiceCallTest extends TestCase
{
    /** @var ServiceCall */
    protected $call;

    public function setUp(): void
    {
        $this->call = new ServiceCall(
            $this->service = $this->getMockBuilder(ServiceWrapper::class)->disableOriginalConstructor()->getMock(),
            $this->request = $this->getMockBuilder(RequestInterface::class)->getMock(),
            $this->response = $this->getMockBuilder(ResponseInterface::class)->getMock(),
            $this->parent = $this->getMockBuilder(ServiceCallInterface::class)->disableOriginalConstructor()->getMock(),
            false
        );
    }

    public function testConstructWithoutTerminating()
    {
        $call = new ServiceCall(
            $this->getMockBuilder(ServiceWrapper::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(RequestInterface::class)->getMock(),
            $this->getMockBuilder(ResponseInterface::class)->getMock()
        );
        $this->assertFalse($call->isTerminating());
    }

    public function testConstructWithoutParent()
    {
        $call = new ServiceCall(
            $this->getMockBuilder(ServiceWrapper::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(RequestInterface::class)->getMock(),
            $this->getMockBuilder(ResponseInterface::class)->getMock()
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
        $this->assertFalse($this->call->isTerminating());
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

    public function testSetAndGetInfo()
    {
        $this->call->setInfo('foo', 'Foo');
        $this->assertEquals('Foo', $this->call->getInfo('foo'));
    }

    public function testGetInfoFallback()
    {
        $this->assertEquals('Fallback', $this->call->getInfo('foo', 'Fallback'));
    }
}
