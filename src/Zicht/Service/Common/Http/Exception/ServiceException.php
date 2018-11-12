<?php
/**
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Http\Exception;

use GuzzleHttp\Message\ResponseInterface;

/**
 * The service exception is thrown whenever the server responds with a non-success status.
 */
class ServiceException extends \UnexpectedValueException
{
    private $response;

    /**
     * Set the response that caused the exception
     *
     * @param ResponseInterface $response
     * @return void
     */
    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;
    }


    /**
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }
}
