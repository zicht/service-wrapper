<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace ZichtTest\Service\Common\Caching;

use PHPUnit\Framework\TestCase;
use Zicht\Service\Common\Cache\CacheKey;

class CacheKeyTest extends TestCase
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

    public function testCacheKeyContainsReadableAttributeValuesIfNotTooLong()
    {
        $key = new CacheKey('foo');
        $key->addAttribute('bar', ['key1' => 'baz', 'key2' => 'bat', 'key3' => 'qux']);

        // contains foo, bar, ... and qux
        $this->assertTrue(strpos((string)$key, 'foo') !== false);
        $this->assertTrue(strpos((string)$key, 'bar') !== false);
        $this->assertTrue(strpos((string)$key, 'key1') !== false);
        $this->assertTrue(strpos((string)$key, 'baz') !== false);
        $this->assertTrue(strpos((string)$key, 'key2') !== false);
        $this->assertTrue(strpos((string)$key, 'bat') !== false);
        $this->assertTrue(strpos((string)$key, 'key3') !== false);
        $this->assertTrue(strpos((string)$key, 'qux') !== false);
    }

    public function testCacheKeyHashesAttributesIfTooLong()
    {
        $loremIpsum = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut vel libero eget elit lacinia egestas id sit amet mauris. Donec gravida libero vel quam convallis commodo. Vivamus dictum purus nec magna elementum sodales. Suspendisse potenti. Ut arcu tellus, elementum in commodo a, cursus tempor eros. Pellentesque non ex id risus sollicitudin egestas. In suscipit lorem nunc, ac posuere urna cursus vitae. Vivamus ex massa, hendrerit pellentesque dui sit amet, porta placerat turpis. Curabitur posuere, ligula a vehicula faucibus, metus libero consectetur turpis, sed imperdiet metus quam et urna. Donec dictum et tortor ut dictum. Nulla a hendrerit lacus.';
        $key = new CacheKey('foo');
        $key->addAttribute('loremIpsum', $loremIpsum);

        // contains foo
        $this->assertTrue(strpos((string)$key, 'foo') !== false);
        // does not contain loremIpsum
        $this->assertTrue(strpos((string)$key, 'loremIpsum') === false);
    }
}
