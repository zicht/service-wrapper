<?php

namespace SroTest\Service;

use \PHPUnit_Framework_TestCase;
use \Sro\Service\Loggable;
use \Sro\Service\ServiceException;
use \Sro\Util\Guid;

/**
 * @covers Sro\Service\ServiceException
 */
class ServiceExceptionTest extends PHPUnit_Framework_TestCase {
    public function testBuildUnspecifiedException()
    {
        $fault = new \SoapFault('fault-code', 'fault-string');
        $exception = ServiceException::buildException($fault, null);
        $this->assertInstanceOf('\Sro\Service\ServiceException', $exception);
        $this->assertEquals($fault, $exception->getCause());
        $this->assertEquals('fault-string', $exception->getMessage());
        $this->assertEquals('fault-string', $exception->getLocalizedMessage());
        $this->assertEquals(Loggable::ERROR, $exception->getLogLevel());
        $this->assertEquals('fault-code', $exception->getFaultCode());
    }

    public function testBuildAuthenticationException()
    {
        $detail = (object)array(
            'AuthenticationFault' => (object)array(
                    'CaseId' => Guid::random(),
                    'LocalizedText' => 'fault-localized'));
        $fault = new \SoapFault('fault-code', 'fault-string', null, $detail);
        $exception = ServiceException::buildException($fault, null);
        $this->assertInstanceOf('\Sro\Service\ServiceException', $exception);
        $this->assertInstanceOf('\Sro\Service\DomainExceptions\AuthenticationFault', $exception);
        $this->assertEquals($fault, $exception->getCause());
        $this->assertEquals('fault-string', $exception->getMessage());
        $this->assertEquals('fault-localized', $exception->getLocalizedMessage());
        $this->assertEquals(Loggable::DEBUG, $exception->getLogLevel());
        $this->assertEquals('fault-code', $exception->getFaultCode());
    }
}