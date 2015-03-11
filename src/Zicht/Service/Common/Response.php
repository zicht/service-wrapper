<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */
namespace Zicht\Service\Common;

use \Zicht\Util\Debug;

/**
 * SOAP Response wrapper
 */
class Response implements ResponseInterface
{
    private $response;
    private $error;

    /**
     * Constructor.
     *
     * @param mixed $response
     * @param mixed $error
     */
    public function __construct($response = null, $error = null)
    {
        $this->setResponse($response);
        $this->setError($error);
    }


    /**
     * Set the response object
     *
     * @param mixed $response
     * @return void
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }


    /**
     * Set a response error
     *
     * @param mixed $error
     * @return void
     */
    public function setError($error)
    {
        $this->error = $error;
    }


    /**
     * Checks if the response is a error
     *
     * @return bool
     */
    public function isError()
    {
        return $this->error !== null;
    }


    /**
     * Returns the error, or null if not set.
     *
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }


    /**
     * Returns the response object
     *
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }


    /**
     * Converts the response to a string
     *
     * @return string
     */
    public function __toString()
    {
        return Debug::dump($this->response, 4);
    }


    /**
     * @{inheritDoc}
     */
    public function getPropertyDeep(array $path)
    {
        $ptr = $this->response;
        foreach ($path as $key) {
            if (is_object($ptr) && isset($ptr->$key)) {
                $ptr =& $ptr->$key;
            } elseif (is_array($ptr) && isset($ptr[$key])) {
                $ptr =& $ptr[$key];
            } else {
                return null;
            }
        }

        return $ptr;
    }


    /**
     * @{inheritDoc}
     */
    public function setPropertyDeep(array $path, $value)
    {
        $ptr =& $this->response;
        foreach ($path as $key) {
            if (is_object($ptr)) {
                if (!isset($ptr->$key)) {
                    $ptr->$key = array();
                }
                $ptr =& $ptr->$key;
            } elseif (is_array($ptr)) {
                if (!isset($ptr[$key])) {
                    $ptr[$key] = array();
                }
                $ptr =& $ptr[$key];
            }
        }
        $ptr = $value;
    }
}
