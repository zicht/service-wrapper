<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Http;

use GuzzleHttp\Message\ResponseInterface;

/**
 * XML implementation for the REST service.
 */
class XmlRest extends AbstractRest
{
    /**
     * {@inheritdoc}
     */
    protected function parseResponse(ResponseInterface $response)
    {
        if (!preg_match('!^(application|text)/xml!', $response->getHeader('Content-Type'))) {
            $ex = new Exception\ServiceException(
                sprintf(
                    'Expected XML content type from server, but got: %s, url:',
                    $response->getHeader('Content-Type'),
                    $response->getEffectiveUrl()
                ),
                500
            );
            $ex->setResponse($response);
            throw $ex;
        }

        return new \SimpleXMLElement($response->getBody()->getContents());
    }
}
