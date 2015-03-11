<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */
namespace Zicht\Service\Common;


/**
 * A request wrapper class for any request that gets sent to the Soap backend
 */
class Request implements RequestInterface
{
    /**
     * The service method name
     *
     * @var string
     */
    protected $method;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * @var array
     */
    protected $attributes;

    /**
     * Constructor.
     *
     * @param string $method
     * @param array $parameters
     * @param array $attributes
     */
    public function __construct($method, array $parameters = array(), $attributes = array())
    {
        $this->setMethod($method);
        $this->setParameters($parameters);
        $this->setAttributes($attributes);
    }

    /**
     * Returns the SOAP method name.
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Returns the call's parameters.
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @{inheritDoc}
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * @{inheritDoc}
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * @{inheritDoc}
     */
    public function getParameterDeep(array $path)
    {
        $ptr = $this->parameters;
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
    public function setParameterDeep(array $path, $value)
    {
        $ptr =& $this->parameters;
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

    /**
     * @{inheritDoc}
     */
    public function isMethod($method)
    {
        return strcasecmp($method, $this->method) == 0;
    }

    /**
     * @{inheritDoc}
     */
    public function isAnyMethod(array $methods)
    {
        $ret = false;
        foreach ($methods as $method) {
            if ($ret = $this->isMethod($method)) {
                break;
            }
        }
        return $ret;
    }


    /**
     * Returns a string representation of the request.
     *
     * @return string
     */
    public function __toString()
    {
        $ret = $this->getMethod() . '(' . "\n";
        $ret .= preg_replace(
            '/^/m',
            '    ', json_encode(
                $this->getParameters(),
                defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0,
                4
            )
        );
        $ret .= ')';
        return $ret;
    }

    /**
     * @param array $attributes
     * @return void
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Checks if an attribute is set.
     *
     * @param string $key
     * @return bool
     */
    public function hasAttribute($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getAttribute($key, $default = null)
    {
        return isset($this->attributes[$key]) ? $this->attributes[$key] : $default;
    }
}