<?php
/**
 * @author Gerard van Helden <gerard@zicht.nl>
 * @copyright Zicht Online <http://zicht.nl>
 */

namespace Zicht\Service\Common\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Stream\Stream;
use Zicht\Itertools as iter;
use Zicht\Itertools\lib\ChainIterator;

/**
 * Wraps an HTTP client for common REST calls.
 */
abstract class AbstractRest
{
    /**
     * Constructor
     *
     * @param string $baseUrl
     * @param Client|null $client
     */
    public function __construct($baseUrl, Client $client = null)
    {
        $this->baseUrl = $baseUrl;

        $this->client = ($client ?: new Client(['defaults' => ['timeout' => 9]]));
    }


    /**
     * Do a GET request
     *
     * @param string $path
     * @param array|ChainIterator $parameters
     * @return mixed
     */
    public function get($path, $parameters = [])
    {
        return $this->send('GET', $path, $parameters);
    }

    /**
     * Do a GET request
     *
     * @param string $path
     * @param array|ChainIterator $parameters
     * @return mixed
     */
    public function head($path, $parameters = [])
    {
        return $this->send('HEAD', $path, $parameters);
    }

    /**
     * Do a PUT request
     *
     * @param string $path
     * @param array|ChainIterator $parameters
     * @param array $payload
     * @return mixed
     */
    public function put($path, $parameters = [], $payload = [])
    {
        return $this->send('PUT', $path, $parameters, $payload);
    }

    /**
     * Do a POST request
     *
     * @param string $path
     * @param array|ChainIterator $parameters
     * @param array $payload
     * @return mixed
     */
    public function post($path, $parameters = [], $payload = [])
    {
        return $this->send('POST', $path, $parameters, $payload);
    }

    /**
     * Do a PATCH request
     *
     * @param string $path
     * @param array|ChainIterator $parameters
     * @param array $payload
     * @return mixed
     */
    public function patch($path, $parameters = [], $payload = [])
    {
        return $this->send('PATCH', $path, $parameters, $payload);
    }

    /**
     * Do a DELETE request
     *
     * @param string $path
     * @param array|ChainIterator $parameters
     * @return mixed
     */
    public function delete($path, $parameters = [])
    {
        return $this->send('DELETE', $path, $parameters);
    }


    /**
     * Send the request to the backend and parse it's response. HTTP exceptions are caught and responses
     * returned as-is
     *
     * @param string $method
     * @param string $path
     * @param array|ChainIterator $parameters
     * @param array $payload
     * @return mixed
     */
    protected function send($method, $path, $parameters, $payload = [])
    {
        $request = $this->createRequest($method, $path, $parameters, $payload);

        try {
            $response = $this->client->send($request);
        } catch (RequestException $e) {
            $response = $e->getResponse();
        }

        if (!$response) {
            return null;
        }

        return $this->parseResponse($response);
    }


    /**
     * Create a request by formatting the parameters in the expected format (either as part 
     * of the body (PUT, POST), or as part of the query string (DELETE, GET)
     *
     * @param string $method
     * @param string $path
     * @param array $parameters
     * @param array $payLoad
     * @return \GuzzleHttp\Message\RequestInterface
     */
    protected function createRequest($method, $path, $parameters, $payLoad = [])
    {
        $request = $this->client->createRequest($method);

        $request->setUrl($this->composeUrl($path, $parameters));

        switch ($method) {
            case 'PUT':
            case 'POST':
            case 'PATCH':
                $request->setHeader('Content-Type', 'application/x-www-form-urlencoded');
                $request->setBody(Stream::factory(http_build_query($payLoad)));
                break;
        }

        return $request;
    }

    /**
     * Construct a url based on path and parameters, prepended with the base url
     *
     * @param string $path
     * @param mixed[] $parameters
     * @return string
     */
    protected function composeUrl($path, $parameters)
    {
        if (sizeof($parameters)) {
            $encodeParameter = function ($value, $key) {
                return sprintf('%s=%s', $key, urlencode($value));
            };
            $concat = function ($a, $b) {
                return sprintf('%s&%s', $a, $b);
            };

            $encodedParameters = iter\reduce(iter\map($encodeParameter, $parameters), $concat);
            return sprintf('%s%s?%s', $this->baseUrl, ltrim($path, '/'), $encodedParameters);
        } else {
            return sprintf('%s%s', $this->baseUrl, ltrim($path, '/'));
        }
    }

    /**
     * Parse a response, e.g. validate its contents and/or parse accordingly
     *
     * @param ResponseInterface $response
     * @return mixed
     */
    abstract protected function parseResponse(ResponseInterface $response);
}
