<?php

use VladimirCatrici\Shopify\Webhook;

require_once dirname(__FILE__) . '/../vendor/autoload.php';
require_once dirname(__FILE__) . '/bootstrap.php';

echo json_encode([
    'topic' => Webhook::getTopic(),
    'shop_domain' => Webhook::getShopDomain(),
    'api_version' => Webhook::getApiVersion(),
    'body' => Webhook::getData(), // JSON string
    'data_arr' => Webhook::getDataAsArray(),
    'validation' => Webhook::validate('S%do$Eq5PawfdnA%chEGRcj8Q@ANPA2h')
]);
