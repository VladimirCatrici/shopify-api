<?php

namespace VladimirCatrici\Shopify\API;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class RequestException extends Exception {
    /**
     * @var Client
     */
    private $client;

    /**
     * @var Response
     */
    private $response;

    public function __construct(Client $client, \GuzzleHttp\Exception\RequestException $previous = null) {
        $this->client = $client;
        $this->response = $previous->getResponse();
        parent::__construct($this->response->getBody(), $this->response->getStatusCode(), $previous);
    }

    /**
     * @return Response
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     * @return string
     */
    public function getDetailsJson() {
        $body = $this->response->getBody();
        $body->seek(0);
        return json_encode([
            'msg' => parent::getPrevious()->getMessage(),
            'request' => $this->client->getConfig(),
            'response' => [
                'code' => $this->response->getStatusCode(),
                'body' => $body->getContents(),
                'headers' => $this->response->getHeaders()
            ]
        ]);
    }
}