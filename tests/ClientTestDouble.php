<?php

namespace ShopifyAPI\Tests;

use VladimirCatrici\Shopify\Client;
use VladimirCatrici\Shopify\ClientConfig;

class ClientTestDouble extends Client
{
    public function __construct(ClientConfig $config) {
        parent::__construct($config);
    }

    public function getGuzzleClient()
    {
        return $this->client;
    }
}
