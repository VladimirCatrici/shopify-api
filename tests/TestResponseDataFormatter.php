<?php


namespace ShopifyAPI\Tests;


use VladimirCatrici\Shopify\Response\ResponseDataFormatterInterface;

class TestResponseDataFormatter implements ResponseDataFormatterInterface {
    public function output($data) {
        return json_decode($data);
    }
}