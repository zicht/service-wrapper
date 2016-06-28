<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Cache;

/**
 * Storage implementation for file (disk) storage
 */
class FileStorage implements Storage
{
    /**
     * Construct the storage
     *
     * @param string $path
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($path)
    {
        $this->path = rtrim($path, '/');
        if (!is_dir($this->path) && !@mkdir($this->path, 0777 ^ umask(), true)) {
            throw new \InvalidArgumentException("$path does not exist");
        }
    }


    /**
     * Read a response from the cache container  object.
     *
     * @param string $key
     * @return mixed
     */
    public function read($key)
    {
        $result = @unserialize(file_get_contents($this->getFilePath($key)));
        return $result['data'];
    }


    /**
     * Write a response to the cache. The TTL is ignored and only used at read time
     *
     * @param string $key
     * @param mixed $data
     * @param int $ttl
     * @return void
     */
    public function write($key, $data, $ttl)
    {
        // suppress errors
        @file_put_contents(
            $this->getFilePath($key),
            serialize(
                array(
                    'T' => time(),
                    'data' => $data
                )
            )
        );
    }


    /**
     * Checks if the filesystem has the passed request cached. The TTL provided is matched against the file's mtime.
     *
     * @param string $key
     * @param int $ttl
     * @return bool
     */
    public function isValid($key, $ttl)
    {
        $ret = false;
        $file = $this->getFilePath($key);
        if (is_file($file)) {
            $valid = true;

            if (null !== $ttl && filemtime($file) < time() - $ttl) {
                $valid = false;
            }

            if ($valid) {
                $result = @unserialize(file_get_contents($this->getFilePath($key)));

                if (!is_array($result) || !array_key_exists('data', $result)) {
                    $valid = false;
                }
            }

            if (!$valid) {
                $this->invalidate($key);
            }

            $ret = $valid;
        }
        return $ret;
    }

    /**
     * Purges the specified object from cache
     *
     * @param string $key
     * @return void
     */
    public function invalidate($key)
    {
        @unlink($this->getFilePath($key));
    }


    /**
     * Returns a path to the file corresponding with the cache key
     *
     * @param string $key
     * @return string
     */
    public function getFilePath($key)
    {
        $key = preg_replace('/[^a-z0-9_.-]/i', '_', $key);
        if (strlen($key) > 60) {
            $key = substr($key, 0, 30) . sha1($key);
        }
        $subdir = join('/', preg_split('//', substr($key, 0, min(4, strlen($key)))));
        $path = $this->path . '/' . trim($subdir, '/');
        if (!is_dir($path)) {
            @mkdir($path, 0777 ^ umask(), true);
        }
        return rtrim($path, '/') . '/' . $key;
    }


    /**
     * Returns all keys in storage
     *
     * @return array|\Traversable
     */
    public function getKeys()
    {
        $ret = array();
        // this might need to be refactored to a custom lazy-loading iterator if the cache gets real big.
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->path)) as $file) {
            if ($file->isFile()) {
                $ret[]= $file->getBasename();
            }
        }
        return $ret;
    }
}
