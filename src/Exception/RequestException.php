<?php

declare(strict_types=1);

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
        parent::__construct((string) $this->response->getBody(), $this->response->getStatusCode(), $previous);
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return string
     */
    public function getDetailsJson(): string
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
            'response' => [
                'code' => $this->response->getStatusCode(),
                'body' => (string) $this->response->getBody(),
                'headers' => $this->response->getHeaders()
            ]
        ]);
    }
}
