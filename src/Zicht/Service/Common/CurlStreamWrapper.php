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
    /**
     * @var array
     */
    private static $rewrites = [];
    private static $protocols = [];

    /**
     * Register the wrapper.
     *
     * @param string[] $rewrites
     * @param string[] $protocols
     * @return void
     */
    public static function register(array $rewrites = [], $protocols = ['http', 'https'])
    {
        // we unregister the current HTTP wrapper
        foreach ($protocols as $protocol) {
            @stream_wrapper_unregister($protocol);
        }

        self::$protocols = $protocols;
        self::$rewrites = $rewrites;

        // we register the new HTTP wrapper
        foreach ($protocols as $protocol) {
            @stream_wrapper_register($protocol, __CLASS__);
        }
    }


    /**
     * Unregisters the stream wrapper.
     *
     * @return void
     */
    public static function unregister()
    {
        // we unregister the current HTTP wrapper
        foreach (self::$protocols as $protocol) {
            @stream_wrapper_unregister($protocol);
            @stream_wrapper_restore($protocol);
        }

        self::$rewrites = [];
    }


    private $path;
    private $mode;
    private $options;
    private $opened_path;
    private $buffer;
    private $pos;
    private $ch;

    /**
     * Open the stream
     *
     * @param string $path
     * @param mixed $mode
     * @param mixed $options
     * @param string $opened_path
     * @return bool
     */
    // @codingStandardsIgnoreStart
    public function stream_open($path, $mode, $options, $opened_path)
    {
    // @codingStandardsIgnoreEnd
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
    // @codingStandardsIgnoreStart
    public function stream_close()
    {
    // @codingStandardsIgnoreEnd
        curl_close($this->ch);
    }

    /**
     * Read the stream
     *
     * @param int $count number of bytes to read
     * @return string content from pos to count
     */
    // @codingStandardsIgnoreStart
    // @codeCoverageIgnoreStart
    public function stream_read($count)
    {
    // @codingStandardsIgnoreEnd
        if (strlen($this->buffer) == 0) {
            return false;
        }

        $read = substr($this->buffer, $this->pos, $count);

        $this->pos += $count;

        return $read;
    }
    // @codeCoverageIgnoreEnd

    /**
     * write the stream
     *
     * @param string $data
     * @return string content from pos to count
     */
    // @codingStandardsIgnoreStart
    // @codeCoverageIgnoreStart
    public function stream_write($data)
    {
    // @codingStandardsIgnoreEnd
        if (strlen($this->buffer) == 0) {
            return false;
        }
        return true;
    }
    // @codeCoverageIgnoreEnd


    /**
     * true if eof else false
     *
     * @return bool
     */
    // @codingStandardsIgnoreStart
    public function stream_eof()
    {
    // @codingStandardsIgnoreEnd
        if ($this->pos > strlen($this->buffer)) {
            return true;
        }

        return false;
    }

    /**
     * @return int the position of the current read pointer
     */
    // @codingStandardsIgnoreStart
    // @codeCoverageIgnoreStart
    public function stream_tell()
    {
    // @codingStandardsIgnoreEnd
        return $this->pos;
    }
    // @codeCoverageIgnoreEnd

    /**
     * Flush stream data
     */
    // @codingStandardsIgnoreStart
    public function stream_flush()
    {
    // @codingStandardsIgnoreEnd
        $this->buffer = null;
        $this->pos = null;
    }

    /**
     * Stat the file, return only the size of the buffer
     *
     * @return array stat information
     */
    // @codingStandardsIgnoreStart
    public function stream_stat()
    {
    // @codingStandardsIgnoreEnd
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
    // @codingStandardsIgnoreStart
    public function url_stat($path, $flags)
    {
    // @codingStandardsIgnoreEnd
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
        //@codeCoverageIgnoreStart
        if ($this->buffer) {
            return;
        }
        //@codeCoverageIgnoreEnd

        foreach (self::$rewrites as $pattern => $replacement) {
            $location = preg_replace($pattern, $replacement, $location);
        }

        $this->ch = curl_init($location);

        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->buffer = curl_exec($this->ch);
        $this->pos = 0;
    }
}
