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
    use FreezableTrait, NestedValueTrait;

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
    public function __construct($method, array $parameters = [], $attributes = [])
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
        $this->assertNotFrozen();

        $this->method = $method;
    }

    /**
     * @{inheritDoc}
     */
    public function setParameters($parameters)
    {
        $this->assertNotFrozen();

        $this->parameters = $parameters;
    }

    /**
     * @{inheritDoc}
     */
    public function getParameterDeep(array $path)
    {
        return $this->getValueFromPath($path, $this->parameters);
    }


    /**
     * @{inheritDoc}
     */
    public function setParameterDeep(array $path, $value)
    {
        $this->assertNotFrozen();

        $this->parameters = $this->setValueDeep($this->parameters, $path, $value);
    }

    /**
     * Helper function to set a deep value on an object recursively.
     *
     * This was rewritten from a former iterative implementation which worked with reference that broke
     * stuff in such weird proportions that I've spent an entire day finding out what the heck was going on.
     *
     * In PHP7 that is.
     *
     * @param mixed $subject
     * @param array $path
     * @param mixed $value
     * @return mixed
     */
    private function setValueDeep($subject, $path, $value)
    {
        if (count($path) === 0) {
            return $value;
        } else {
            $property = array_shift($path);
            if (is_object($subject)) {
                if (!isset($subject->$property)) {
                    $subject->$property = [];
                }
                $subject->$property = $this->setValueDeep($subject->$property, $path, $value);
            } elseif (is_array($subject)) {
                if (!isset($subject[$property])) {
                    $subject[$property] = [];
                }
                $subject[$property] = $this->setValueDeep($subject[$property], $path, $value);
            }
        }
        return $subject;
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
        return sprintf('%s(`%s`)', $this->getMethod(), json_encode($this->getParameters(), 0, 2));
    }

    /**
     * @param array $attributes
     * @return void
     */
    public function setAttributes(array $attributes)
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

    /**
     * @{inheritDoc}
     */
    public function getAttributeDeep(array $path)
    {
        return $this->getValueFromPath($path, $this->attributes);
    }
}
