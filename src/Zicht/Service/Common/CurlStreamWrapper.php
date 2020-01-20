<?php
/**
 * @copyright Zicht online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

/**
 * Class Stream
 */
class CurlStreamWrapper
{
    /** @var array */
    private static $urlRewrites = [];

    /** @var array */
    private static $contentRewrites = [];

    /** @var array */
    private static $protocols = [];

    /** @var string */
    private $path;

    /** @var mixed */
    private $mode;

    /** @var mixed */
    private $options;

    /** @var string */
    private $opened_path;

    /** @var string|null */
    private $buffer;

    /** @var int|null */
    private $pos;

    /** @var int|resource */
    private $ch;

    /**
     * Register the wrapper.
     *
     * @param string[] $urlRewrites
     * @param [] $contentRewrites
     * @param string[] $protocols
     * @return void
     */
    public static function register(array $urlRewrites = [], array $contentRewrites = [], array $protocols = ['http', 'https'])
    {
        // we unregister the current HTTP wrapper
        foreach ($protocols as $protocol) {
            @stream_wrapper_unregister($protocol);
        }

        self::$urlRewrites = $urlRewrites;
        self::$contentRewrites = $contentRewrites;
        self::$protocols = $protocols;

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

        self::$urlRewrites = [];
        self::$contentRewrites = [];
        self::$protocols = [];
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
        $stat = [
            'size' => strlen($this->buffer),
        ];

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
        $stat = [
            'size' => strlen($this->buffer),
        ];

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

        // process url rewrites before downloading
        foreach (self::$urlRewrites as $pattern => $replacement) {
            $location = preg_replace($pattern, $replacement, $location);
        }

        $this->ch = curl_init($location);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $this->buffer = curl_exec($this->ch);
        $this->pos = 0;

        // process content rewrites after downloading
        foreach (self::$contentRewrites as $contentRewrite) {
            if (preg_match($contentRewrite['file_pattern'], $location)) {
                $this->buffer = preg_replace($contentRewrite['pattern'], $contentRewrite['replacement'], $this->buffer);
            }
        }
    }
}
