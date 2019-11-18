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
        $body = $this->response->getBody();
        $body->seek(0);
        parent::__construct($body->getContents(), $this->response->getStatusCode(), $previous);
    }

    /**
     * @return Response
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * @return string
     */
    public function getDetailsJson(): string
    {
        $uri = $this->request->getUri();
        $output = [
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
                'body' => $this->request->getBody()->getContents()
            ]
        ];
        if (!empty($this->response)) {
            $body = $this->response->getBody();
            $body->seek(0);
            $output['response'] = [
                'code' => $this->response->getStatusCode(),
                'body' => $body->getContents(),
                'headers' => $this->response->getHeaders()
            ];
        }
        return json_encode($output);
    }
}
