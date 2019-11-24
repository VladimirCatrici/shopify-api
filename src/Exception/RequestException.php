<?php

namespace VladimirCatrici\Shopify\Exception;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class RequestException extends Exception
{
    /**
     * @var Client
     */
    private $client;
    /**
     * @var Request
     */
    private $request;
    /**
     * @var Response
     */
    private $response;

    /**
     * RequestException constructor.
     * @param Client $client
     * @param \GuzzleHttp\Exception\RequestException $previous
     */
    public function __construct(Client $client, \GuzzleHttp\Exception\RequestException $previous = null)
    {
        $this->client = $client;
        $this->request = $previous->getRequest();
        $this->response = $previous->getResponse();
        $body = $previous->hasResponse() ? (string) $this->response->getBody() : '';
        parent::__construct($body, $this->response->getStatusCode(), $previous);
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return string
     */
    public function getDetailsJson()
    {
        $uri = $this->request->getUri();
        return json_encode([
            'msg' => parent::getPrevious()->getMessage(),
            'request' => [
                'method' => $this->request->getMethod(),
                'uri' => [
                    'scheme'    => $uri->getScheme(),
                    'host'      => $uri->getHost(),
                    'path'      => $uri->getPath(),
                    'port'      => $uri->getPort(),
                    'query'     => $uri->getQuery()
                ],
                'headers' => $this->request->getHeaders(),
                'body' => (string) $this->request->getBody()
            ],
            'response' => $this->response !== null ? [
                'code' => $this->response->getStatusCode(),
                'body' => (string) $this->response->getBody(),
                'headers' => $this->response->getHeaders()
            ] : ''
        ]);
    }
}
