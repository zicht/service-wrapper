<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Http;

use GuzzleHttp\Message\ResponseInterface;

/**
 * JSON implementation for the REST service.
 */
class JsonRest extends AbstractRest
{
    protected function parseResponse(ResponseInterface $response)
    {
        if (!preg_match('!^application/json!', $response->getHeader('Content-Type'))) {
            $ex = new Exception\ServiceException(sprintf(
                'Expected JSON content type from server, but got: %s, url:',
                $response->getHeader('Content-Type'),
                $response->getEffectiveUrl()
            ), 500);
            $ex->setResponse($response);
            throw $ex;
        }

        return json_decode($response->getBody()->getContents());
    }
}