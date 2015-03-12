<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common\Observers;
use PHPUnit_Framework_TestCase;

use \Monolog\Logger as Monolog;
use \Zicht\Service\Common\Response;
use \Zicht\Service\Common\Request;
use \Zicht\Service\Common\ServiceCall;
use \Zicht\Service\Common\Observers\Timer;

class TimerStub extends Timer {
    public $return = null;

    protected function _stop($serviceMethod) {
        return $this->return;
    }
}

class TimerStub2 extends Timer {
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
        $timer = new Timer(array(0 => 'level 1', 500 => 'level 2', 2000 => 'level 3'));
        $this->assertEquals('level 1', $timer->getLogLevel(0.1));
        $this->assertEquals('level 2', $timer->getLogLevel(0.7));
        $this->assertEquals('level 3', $timer->getLogLevel(2.5));
    }

    function testCustomThresholdsWillBeSorted() {
        $timer = new Timer( array(500 => 'level 2', 2000 => 'level 3', 0 => 'level 1'));
        $this->assertEquals('level 1', $timer->getLogLevel(0.1));
        $this->assertEquals('level 2', $timer->getLogLevel(0.7));
        $this->assertEquals('level 3', $timer->getLogLevel(2.5));
    }
}