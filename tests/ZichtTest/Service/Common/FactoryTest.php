<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */


namespace ZichtTest\Service\Common;

use Zicht\Service\Common\Factory;

class SomeService
{
    public function __construct($a, $b)
    {
        $this->a = $a;
        $this->b = $b;
    }
}

/**
 * @covers Zicht\Service\Common\Factory
 */
class FactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testFactory()
    {
        $f = new Factory(SomeService::class, ['a', 'b']);
        $service = $f->createService();
        $this->assertInstanceOf(SomeService::class, $service);
        $this->assertEquals('a', $service->a);
        $this->assertEquals('b', $service->b);
    }
}