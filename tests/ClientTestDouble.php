<?php

namespace ShopifyAPI\Tests;

use VladimirCatrici\Shopify\Client;
use VladimirCatrici\Shopify\ClientConfig;

class ClientTestDouble extends Client
{
    private $handler;
    /**
     * ClientTestDouble constructor.
     * @param ClientConfig $config
     * @param null $handler callback Guzzle HTTP handler (for tests only)
     */
    public function __construct(ClientConfig $config, $handler = null)
    {
        $this->handler = $handler;
        parent::__construct($config);
        $this->initClient();
    }

    protected function initClient()
    {
        $config = $this->getConfig();
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $config->getBaseUrl(),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $config->getAccessToken()
            ],
            'handler' => $this->handler
        ]);
    }

    public function getGuzzleClient()
    {
        return $this->client;
    }
}
