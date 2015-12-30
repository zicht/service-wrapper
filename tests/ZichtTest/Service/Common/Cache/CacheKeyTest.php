<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */
namespace ZichtTest\Service\Common\Caching;

use Zicht\Service\Common\Cache\CacheKey;

class CacheKeyTest extends \PHPUnit_Framework_TestCase
{
    public function testCacheKeyContainsName()
    {
        $key = new CacheKey('foo');

        $this->assertTrue(strpos((string)$key, 'foo') !== false);
    }


    public function testCacheKeyContainsAttributeKeys()
    {
        $key = new CacheKey('foo');
        $key->addAttribute('bar', 'baz');

        $this->assertTrue(strpos((string)$key, 'bar') !== false);
    }


    public function testCacheKeyContainsAttributeValues()
    {
        $key = new CacheKey('foo');
        $key->addAttribute('bar', 'baz');

        $this->assertTrue(strpos((string)$key, 'baz') !== false);
    }


    public function testCacheKeyContainsAttributeValuesIfArray()
    {
        $key = new CacheKey('foo');
        $key->addAttribute('bar', ['baz', 'bat', 'qux']);

        $this->assertTrue(strpos((string)$key, 'baz') !== false);
    }


    public function testCacheKeyContainsAttributeValuesIfHash()
    {
        $key = new CacheKey('foo');
        $key->addAttribute('bar', ['key1' => 'baz', 'key2' => 'bat', 'key3' => 'qux']);

        $this->assertTrue(strpos((string)$key, 'baz') !== false);
        $this->assertTrue(strpos((string)$key, 'key1') !== false);
    }


    public function testCacheKeyHashesValuesIfTooDeep()
    {
        $key = new CacheKey('foo');
        $key->addAttribute('bar', [['key1' => 'baz', 'key2' => 'bat', 'key3' => 'qux']]);

        $this->assertTrue(strpos((string)$key, 'baz') === false);
        $this->assertTrue(strpos((string)$key, 'key1') === false);
    }


    public function testCacheKeyNotHashesValuesIfDepthHigherThanDefault()
    {
        $key = new CacheKey('foo', 2);
        $key->addAttribute('bar', [['key1' => 'baz', 'key2' => 'bat', 'key3' => 'qux']]);

        $this->assertTrue(strpos((string)$key, 'baz') !== false);
        $this->assertTrue(strpos((string)$key, 'key1') !== false);
    }
}