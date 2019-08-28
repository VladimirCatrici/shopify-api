<?php

namespace VladimirCatrici\Shopify\API;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\GuzzleException;

class RequestException extends Exception {
    /**
     * @var Client
     */
    private $client;

    /**
     * @var Response
     */
    private $response;

    /**
     * RequestException constructor.
     * @param Client $client
     * @param GuzzleException|\GuzzleHttp\Exception\RequestException $previous
     */
    public function __construct(Client $client, $previous = null) {
        $this->client = $client;
        if (method_exists($previous, 'getResponse')) {
            $this->response = $previous->getResponse();
        }
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
        if (!empty($this->response)) {
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
        } else {
            return json_encode([
                'msg' => parent::getPrevious()->getMessage(),
                'request' => $this->client->getConfig()
            ]);
        }
    }
}