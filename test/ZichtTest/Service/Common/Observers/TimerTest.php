<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace SroTest\Service\Observers;
use PHPUnit_Framework_TestCase;
use \Sro\Service\Request;
use \Sro\Service\Response;
use \Sro\Service\Event;
use \Monolog\Logger as Monolog;
use Sro\Service\Observers\Timer;

class TimerStub extends \Sro\Service\Observers\Timer {
    public $return = null;

    protected function _stop($serviceMethod) {
        return $this->return;
    }
}

class TimerStub2 extends \Sro\Service\Observers\Timer {
    public $calls = array();

    protected function _start($serviceMethod) {
        $this->calls['_start'][]= func_get_args();
    }

    protected function _stop($serviceMethod) {
        $this->calls['_stop'][]= func_get_args();
    }
}


class TimerTest extends PHPUnit_Framework_TestCase {
    function testThresholds() {
        $timer = new Timer();

        foreach (Timer::$defaults as $threshold => $level) {
            $this->assertEquals($level, $timer->getLogLevel(($threshold +1) / 1000));
        }
    }


    function testCustomThresholds() {
        $timer = new \Sro\Service\Observers\Timer(array(0 => 'level 1', 500 => 'level 2', 2000 => 'level 3'));
        $this->assertEquals('level 1', $timer->getLogLevel(0.1));
        $this->assertEquals('level 2', $timer->getLogLevel(0.7));
        $this->assertEquals('level 3', $timer->getLogLevel(2.5));
    }

    function testCustomThresholdsWillBeSorted() {
        $timer = new \Sro\Service\Observers\Timer( array(500 => 'level 2', 2000 => 'level 3', 0 => 'level 1'));
        $this->assertEquals('level 1', $timer->getLogLevel(0.1));
        $this->assertEquals('level 2', $timer->getLogLevel(0.7));
        $this->assertEquals('level 3', $timer->getLogLevel(2.5));
    }
}