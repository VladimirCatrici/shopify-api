<?php

namespace VladimirCatrici\Shopify\Exceptions;

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

    /**
     * RequestException constructor.
     * @param Client $client
     * @param \GuzzleHttp\Exception\RequestException $previous
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
        $output = [
            'msg' => parent::getPrevious()->getMessage(),
            'request' => $this->client->getConfig()
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