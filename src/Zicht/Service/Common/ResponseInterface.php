<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common;

/**
 * Interface for the response
 *
 * @package Zicht\Service\Common
 */
interface ResponseInterface
{
    /**
     * Get the property at the specified path
     *
     * For example:
     * > $response->getPropertyDeep(['response', 'product'])
     *
     * @param array $path
     * @return mixed
     */
    public function getPropertyDeep(array $path);

    /**
     * Get zero of more properties at the specified path.
     *
     * The path indicators ending with '[]' will be assumed to be arrays.
     *
     * For example:
     * > $response->getPropertyDeep(['response', 'product', 'prices[]'])
     *
     * The return value is an array with [$path, $value] tuples.  This
     * $path can be used in both getPropertyDeep and setPropertyDeep.
     *
     * @param array $path
     * @return array
     */
    public function getPropertiesDeep(array $path);

    /**
     * Sets a response property at the specified path.
     *
     * @param array $path
     * @param mixed $value
     * @return void
     */
    public function setPropertyDeep(array $path, $value);

    /**
     * Checks if the call resulted in an error.
     *
     * @return mixed
     */
    public function isError();

    /**
     * @return \Exception
     */
    public function getError();

    /**
     * @return mixed
     */
    public function getResponse();

    /**
     * Set the error in the response
     *
     * @param \Exception $error
     * @return void
     */
    public function setError($error);

    /**
     * @param mixed $response
     * @return mixed
     */
    public function setResponse($response);

    /**
     * @return bool
     */
    public function isCachable();

    /**
     * @param bool $cachable
     * @return void
     */
    public function setCachable($cachable);
}
