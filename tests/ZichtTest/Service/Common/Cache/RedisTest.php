<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

use Zicht\Service\Common\Cache\RedisStorage;

class RedisTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group integration
     */
    public function testIntegration()
    {
        try {
            new \Redis();
        } catch(\Exception $e) {
            $this->markTestSkipped("no Redis connection available, skipping");
        }
        $storage = new RedisStorage('localhost', 'foo');
        $storage->write('bar', 'the data', 10);
        $this->assertEquals('the data', $storage->read('bar'));
        $this->assertTrue($storage->isValid('bar', 1));

        foreach ($storage->getKeys() as $k) {
            $storage->invalidate($k);
        }
    }
}