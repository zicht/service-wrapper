<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common\Caching;

use \PHPUnit_Framework_TestCase;

use \Zicht\Service\Common\Cache\MemoryStorage;

class MemoryStorageTest extends PHPUnit_Framework_TestCase
{
    protected $container;

    /** @var MemoryStorage */
    protected $storage;

    function setUp()
    {
        $this->container = new \stdClass;
        $this->storage = new MemoryStorage($this->container);
    }


    function testReadWillReturnDataKeyFromContainerClass()
    {
        $data = rand(1, 9999);
        $this->container->myKey = array(
            'data' => $data
        );
        $this->assertEquals($data, $this->storage->read('myKey'));
    }


    function testWriteWillSaveDataInContainerClass()
    {
        $data = rand(1, 9999);
        $this->storage->write('myKey', $data, 10);
        $this->assertEquals($data, $this->container->myKey['data']);
        $this->assertEquals(10, $this->container->myKey['ttl']);
        $this->assertGreaterThanOrEqual(time(), $this->container->myKey['time']);
    }


    function testIsValidWillCheckIfKeyExists()
    {
        $this->assertFalse($this->storage->isValid('myKey', 10));
    }


    function testIsValidWillReturnFalseIfFormatIsInvalid()
    {
        $this->container->myKey = array('foo' => 'bar');
        $this->assertFalse($this->storage->isValid('myKey', 10));
    }

    function testIsValidWillRemoveObjectFromStorage()
    {
        $this->container->myKey = array('foo' => 'bar');
        $this->storage->isValid('myKey', 10);
        $this->assertFalse(property_exists($this->container, 'myKey'));
    }


    function testIsValidWillReturnTrueIfTtlIsNull()
    {
        $this->storage->write('myKey', 'a', null);
        $this->assertTrue($this->storage->isValid('myKey', null));
    }


    function testIsValidWillReturnFalseIfObjectExpired()
    {
        $this->storage->write('myKey', 'a', null);
        $this->container->myKey['time'] = time() - 100;
        $this->assertFalse($this->storage->isValid('myKey', 10));
    }

    function testIsValidWillRemoveObjectIfExpired()
    {
        $this->storage->write('myKey', 'a', null);
        $this->container->myKey['time'] = time() - 100;
        $this->storage->isValid('myKey', 10);
        $this->assertFalse(property_exists($this->container, 'myKey'));
    }


    function testIsValidWillLetIsValidTtlPrevail()
    {
        $this->storage->write('myKey', 'a', 200);
        $this->container->myKey['time'] = time() - 100;
        $this->assertFalse($this->storage->isValid('myKey', 10));
        $this->assertFalse(property_exists($this->container, 'myKey'));
    }

    function testGetKeys()
    {
        $this->storage->write('a', '1', 10);
        $this->storage->write('b', '2', 10);
        $this->assertEquals(['a', 'b'], $this->storage->getKeys());
    }
}