<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common\Observers;

use Psr\Log\Test\LoggerInterfaceTest;
use Zicht\Service\Common\Observers\LoggableServiceObserverAdapter;

class LoggerImpl extends LoggableServiceObserverAdapter
{
    public function doLog($level, $message, array $context)
    {
        $this->addLogRecord(...func_get_args());
    }
}


class LoggableServiceObserverAdapterTest extends \PHPUnit_Framework_TestCase
{
    public function testAddLogRecordWithLogger()
    {
        $observer = new LoggerImpl();
        $logger = $this->getMockBuilder('Monolog\Logger')->setMethods(['addRecord'])->getMock();
        $logger->expects($this->once())->method('addRecord')->with('level', 'message', ['the' => 'context']);
        $observer->setLogger($logger);
        $observer->doLog('level', 'message', ['the' => 'context']);
    }
}