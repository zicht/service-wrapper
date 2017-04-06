<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

/**
 * Base interface for the Requests
 *
 * @package Zicht\Service\Common
 */
interface RequestInterface
{
    /**
     * Set the request method name
     *
     * @param string $method
     * @return void
     */
    public function setMethod($method);

    /**
     * Returns the method name
     *
     * @return string
     */
    public function getMethod();

    /**
     * Set an arbitrary request attribute, useful for internal information to be exchanged between observers.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function setAttribute($name, $value);

    /**
     * Returns an arbitrary request attribute.
     *
     * @param string $name
     * @return mixed
     */
    public function getAttribute($name);

    /**
     * Returns whether the specified attribute is set in the request
     *
     * @param string $name
     * @return bool
     */
    public function hasAttribute($name);

    /**
     * Set a request parameter by it's path.
     *
     * @param array $path
     * @param mixed $value
     * @return mixed
     */
    public function setParameterDeep(array $path, $value);

    /**
     * Find a parameter following the specified path.
     *
     * @param array $path
     * @return mixed
     */
    public function getParameterDeep(array $path);

    /**
     * @return array
     */
    public function getParameters();

    /**
     * Set the SOAP parameters
     *
     * @param array $parameters
     * @return void
     */
    public function setParameters($parameters);

    /**
     * Checks if method name is among the supplied methods.
     *
     * @param string[] $methodNames
     * @return bool
     */
    public function isAnyMethod(array $methodNames);

    /**
     * Does a case insensitive comparison of the method name
     *
     * @param string $methodName
     * @return bool
     */
    public function isMethod($methodName);

    /**
     * Freeze the request, i.e. forbid any further changes.
     *
     * @return mixed
     */
    public function freeze();
}
