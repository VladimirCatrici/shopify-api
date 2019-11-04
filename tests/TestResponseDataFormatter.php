<?php


namespace ShopifyAPI\Tests;


use VladimirCatrici\Shopify\Response\ResponseDataFormatterInterface;

class TestResponseDataFormatter implements ResponseDataFormatterInterface {
    public function output(string $data) {
        return json_decode($data);
    }
}