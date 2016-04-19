<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

/**
 * Class Stream
 */
class CurlStreamWrapper
{
    private static $rewrites;

    /**
     * Register the wrapper.
     *
     * @return void
     */
    public static function register(array $rewrites = [])
    {
        // we unregister the current HTTP wrapper
        @stream_wrapper_unregister('http');
        @stream_wrapper_unregister('https');

        self::$rewrites = $rewrites;

        // we register the new HTTP wrapper
        stream_wrapper_register('http', __CLASS__);
        stream_wrapper_register('https', __CLASS__);
    }


    /**
     * Unregisters the stream wrapper.
     *
     * @return void
     */
    public static function unregister()
    {
        stream_wrapper_restore('https');
        stream_wrapper_restore('http');

        self::$rewrites = [];
    }


    private $path;
    private $mode;
    private $options;
    private $opened_path;
    private $buffer;
    private $pos;

    /**
     * Stub for logging
     *
     * @param string $str
     * @return void
     */
    protected function log($str)
    {
    }

    /**
     * Open the stream
     *
     * @param string $path
     * @param mixed $mode
     * @param mixed $options
     * @param string $opened_path
     * @return bool
     */
    public function stream_open($path, $mode, $options, $opened_path)
    {
        $this->path = $path;
        $this->mode = $mode;
        $this->options = $options;
        $this->opened_path = $opened_path;

        $this->createBuffer($path);

        return true;
    }

    /**
     * Close the stream
     *
     * @return void
     */
    public function stream_close()
    {
        curl_close($this->ch);
    }

    /**
     * Read the stream
     *
     * @param int $count number of bytes to read
     * @return string content from pos to count
     */
    public function stream_read($count)
    {
        if (strlen($this->buffer) == 0) {
            return false;
        }

        $read = substr($this->buffer, $this->pos, $count);

        $this->pos += $count;

        return $read;
    }

    /**
     * write the stream
     *
     * @param string $data
     * @return string content from pos to count
     */
    public function stream_write($data)
    {
        if (strlen($this->buffer) == 0) {
            return false;
        }
        return true;
    }


    /**
     * true if eof else false
     *
     * @return bool
     */
    public function stream_eof()
    {
        if ($this->pos > strlen($this->buffer)) {
            return true;
        }

        return false;
    }

    /**
     * @return int the position of the current read pointer
     */
    public function stream_tell()
    {
        return $this->pos;
    }

    /**
     * Flush stream data
     */
    public function stream_flush()
    {
        $this->buffer = null;
        $this->pos = null;
    }

    /**
     * Stat the file, return only the size of the buffer
     *
     * @return array stat information
     */
    public function stream_stat()
    {
        $this->createBuffer($this->path);
        $stat = array(
            'size' => strlen($this->buffer),
        );

        return $stat;
    }

    /**
     * Stat the url, return only the size of the buffer
     *
     * @return array stat information
     */
    public function url_stat($path, $flags)
    {
        $this->createBuffer($path);
        $stat = array(
            'size' => strlen($this->buffer),
        );

        return $stat;
    }

    /**
     * Create the buffer by requesting the url through cURL
     *
     * @param string $location
     */
    private function createBuffer($location)
    {
        if ($this->buffer) {
            return;
        }

        var_dump($location);
        foreach (self::$rewrites as $pattern => $replacement) {
            $location = preg_replace($pattern, $replacement, $location);
        }

        $this->ch = curl_init($location);
        var_dump($location);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->buffer = curl_exec($this->ch);
        $this->pos = 0;
    }
}

