<?php declare(strict_types=1);
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common;

use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\Factory;

class SomeService
{
    public function __construct($a, $b)
    {
        $this->a = $a;
        $this->b = $b;
    }
}

class FactoryTest extends TestCase
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
