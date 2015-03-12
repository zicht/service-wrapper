<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */
namespace ZichtTest\Service\Common\Caching;

use \PHPUnit_Framework_TestCase;

use \Zicht\Service\Common\Cache\FileStorage;

class FileStorageTest extends PHPUnit_Framework_TestCase {
    protected $path = '/tmp/file-storage-test';

    function setUp() {
        if (is_dir($this->path)) {
            $this->markTestSkipped('Dir already exists! Please remove it.');
        }
    }


    function tearDown() {
        if(is_dir($this->path)) {
            shell_exec("rm -r {$this->path}");
        }
    }


    /**
     * @return \Sro\Service\Caching\FileStorage
     */
    function testCreateFileStorageWillCreatePath() {
        $this->assertFalse(is_dir($this->path));
        new FileStorage('/tmp/file-storage-test');
        $this->assertTrue(is_dir($this->path));
    }


    /**
     * @expectedException \InvalidArgumentException
     */
    function testCreateFileStorageWillFailIfDirectoryCannotBeCreated() {
        $this->assertFalse(is_dir($this->path));
        new FileStorage('/this-should-not-be-writable');
    }


    function testGetFilePathWillSplitUpInDirs() {
        $storage = new FileStorage('/tmp/file-storage-test');
        $this->assertEquals('/tmp/file-storage-test/s/o/m/e/some-long-filename', $storage->getFilePath('some-long-filename'));
        $this->assertEquals('/tmp/file-storage-test/k/e/y/key', $storage->getFilePath('key'));
    }


    function testGetKeys() {
        $storage = new FileStorage($this->path);
        $storage->write('some-filename', 1, 100);
        $storage->write('some-other-filename', 1, 100);
        $storage->write('small', 1, 100);
        foreach ($storage->getKeys() as $key) {
            $this->assertTrue($storage->isValid($key, 100));
            $storage->invalidate($key);
            clearstatcache();
            $this->assertFalse($storage->isValid($key, 100));
        }
    }


    function testApi() {
        $storage = new FileStorage('/tmp/file-storage-test');

        $this->assertFalse($storage->isValid('key', 1));
        $value = rand(0, 99999);
        $storage->write('key', $value, 10);
        $this->assertTrue($storage->isValid('key', 1));
        $this->assertEquals($value, $storage->read('key'));
        $storage->invalidate('key');
        $this->assertFalse($storage->isValid('key', 1));


        $storage->write('key', $value, 1);
        touch($storage->getFilePath('key'), time() -3);

        clearstatcache();
        $this->assertFalse($storage->isValid('key', 1));
        $this->assertFalse(is_file($storage->getFilePath('key')));
    }


    function testIsValidWillReturnFalseIfFileContentsAreNotUnserializable() {
        $storage = new FileStorage('/tmp/file-storage-test');

        $this->assertFalse($storage->isValid('key', 1));
        $value = rand(0, 99999);
        $storage->write('key', $value, 10);
        $this->assertTrue($storage->isValid('key', 1));

        file_put_contents($storage->getFilePath('key'), 'foo');
        $this->assertFalse($storage->isValid('key', 1));
    }
}