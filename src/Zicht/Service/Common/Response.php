<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */
namespace Zicht\Service\Common;

use Zicht\Util\Debug;

/**
 * SOAP Response wrapper
 */
class Response implements ResponseInterface
{
    private $response;
    private $error;

    /**
     * By default, a response is cachable, but if any observer fails to add data that is needed in the cache,
     * it may mark a response as 'uncachable', so it does not put incomplete or invalid data in the cache.
     *
     * @var bool
     */
    private $isCachable = true;

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
     * Returns whether this object is (still) cachable
     *
     * @return bool
     */
    public function isCachable()
    {
        return $this->isCachable;
    }


    /**
     * Mark the response as (un)cachable. Use with care: don't set an object to 'cachable' when it's not, because
     * there is probably a good reason it isn't: by default objects are cachable, but observers may mark a response
     * uncachable.
     *
     * @param bool $cachable
     */
    public function setCachable($cachable)
    {
        $this->isCachable = (bool)$cachable;
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
        return $this->getPropertyDeepFrom($this->response, $path);
    }

    /**
     * Returns a property at $propertyPath in the $ptr data
     *
     * @param mixed $ptr
     * @param array $propertyPath
     * @return mixed|null
     */
    protected function getPropertyDeepFrom($ptr, array $propertyPath)
    {
        foreach ($propertyPath as $key) {
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
    public function getPropertiesDeep(array $propertyPath)
    {
        // prepare a nested property path
        $flatPropertyPath = array();
        $nestedPropertyPath = array();
        foreach ($propertyPath as $key) {
            if (is_string($key) && preg_match('/^(.+)\[\]$/', $key, $matches)) {
                $flatPropertyPath [] = $matches[1];
                $nestedPropertyPath [] = array($flatPropertyPath, true);
                $flatPropertyPath = [];
            } else {
                $flatPropertyPath [] = $key;
            }
        }
        if (!empty($flatPropertyPath)) {
            $nestedPropertyPath [] = array($flatPropertyPath, false);
        }

        // create list with raw data and their absolute path
        $pointers = array(array(array(), $this->response));
        foreach ($nestedPropertyPath as list($flatPropertyPath, $multiple)) {
            $newPointers = array();
            foreach ($pointers as list($basePath, $pointer)) {
                $pointer = $this->getPropertyDeepFrom($pointer, $flatPropertyPath);
                if (!is_null($pointer)) {
                    if ($multiple) {
                        if (is_array($pointer)) {
                            foreach ($pointer as $key => $value) {
                                $absolutePath = array_merge($basePath, $flatPropertyPath, array($key));
                                $newPointers [] = array($absolutePath, $value);
                            }
                        }
                    } else {
                        $absolutePath = array_merge($basePath, $flatPropertyPath);
                        $newPointers [] = array($absolutePath, $pointer);
                    }
                }
            }
            $pointers = $newPointers;
        }

        return $pointers;
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
