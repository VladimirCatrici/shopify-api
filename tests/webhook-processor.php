<?php

use VladimirCatrici\Shopify;

require_once dirname(__FILE__) . '/../vendor/autoload.php';
require_once dirname(__FILE__) . '/bootstrap.php';

file_put_contents('php://output', json_encode([
    'topic' => Shopify\Webhook::getTopic(),
    'shop_domain' => Shopify\Webhook::getShopDomain(),
    'api_version' => Shopify\Webhook::getApiVersion(),
    'body' => Shopify\Webhook::getData(), // JSON string
    'data_arr' => Shopify\Webhook::getDataAsArray(),
    'validation' => Shopify\Webhook::validate('S%do$Eq5PawfdnA%chEGRcj8Q@ANPA2h')
]));
