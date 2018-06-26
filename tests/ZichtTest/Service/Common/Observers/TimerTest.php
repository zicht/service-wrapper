<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common\Observers;

use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\LoggerConstants;
use Zicht\Service\Common\Observers\Timer;

class TimerStub extends Timer
{
    public $return = null;

    protected function _stop($serviceMethod)
    {
        return $this->return;
    }
}

class TimerStub2 extends Timer
{
    public $calls = [];

    protected function _start($serviceMethod)
    {
        $this->calls['_start'][] = func_get_args();
    }

    protected function _stop($serviceMethod)
    {
        $this->calls['_stop'][] = func_get_args();
    }
}

class TimerStub3 extends Timer
{
    public $currentTime;

    protected function getCurrentTime()
    {
        return $this->currentTime;
    }
}

/**
 * @covers \Zicht\Service\Common\Observers\Timer
 */
class TimerTest extends TestCase
{
    function testThresholds()
    {
        $timer = new Timer();

        foreach (Timer::$defaults as $threshold => $level) {
            $this->assertEquals($level, $timer->getLogLevel(($threshold + 1) / 1000));
        }
    }


    function testCustomThresholds()
    {
        $timer = new Timer([0 => 'level 1', 500 => 'level 2', 2000 => 'level 3']);
        $this->assertEquals('level 1', $timer->getLogLevel(0.1));
        $this->assertEquals('level 2', $timer->getLogLevel(0.7));
        $this->assertEquals('level 3', $timer->getLogLevel(2.5));
    }

    function testCustomThresholdsWillBeSorted()
    {
        $timer = new Timer([500 => 'level 2', 2000 => 'level 3', 0 => 'level 1']);
        $this->assertEquals('level 1', $timer->getLogLevel(0.1));
        $this->assertEquals('level 2', $timer->getLogLevel(0.7));
        $this->assertEquals('level 3', $timer->getLogLevel(2.5));
    }

    function testDefaultIsDebug()
    {
        $timer = new Timer([]);
        $this->assertEquals(LoggerConstants::DEBUG, $timer->getLogLevel(0.1));
    }


    function testStartStop()
    {
        $delta = rand(0, 100);

        $timer = new TimerStub3();
        $timer->currentTime = 10;
        $timer->start('foo');
        $timer->currentTime = 10 + $delta;
        $this->assertEquals($delta, $timer->stop('foo'));
    }

    function testStopDefaultsToMinusZero()
    {
        $this->assertLessThan(0, (new Timer())->stop('foo'));
    }

    public function testGetCurrentTimeDefault()
    {
        $timer = new Timer();
        $refl = new \ReflectionMethod($timer, 'getCurrentTime');
        $refl->setAccessible(true);

        $t = $refl->invoke($timer);
        usleep(10000);
        $this->assertGreaterThan(0, $refl->invoke($timer) - $t);
    }
}
