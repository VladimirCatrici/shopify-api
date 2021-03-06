<?php

namespace ShopifyAPI\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use VladimirCatrici\Shopify\API;
use VladimirCatrici\Shopify\ClientManager;
use PHPUnit\Framework\TestCase;

class ClientManagerTest extends TestCase {

    public function testThrowsExceptionOnMissingConfigKey() {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/configuration not found/i');
        ClientManager::get('test');
    }

    public function testGet() {
        ClientManager::setConfig('test', [
            'domain' => 'test',
            'access_token' => 'test',
            'max_limit_rate' => 0.9
        ]);
        $api = ClientManager::get('test');
        $this->assertInstanceOf(API::class, $api);
    }

    public function testPassingResponseDataFormatterOption() {
        $mock = new MockHandler();
        $handler = HandlerStack::create($mock);
        ClientManager::setConfig('test-response-data-formatter', [
            'domain' => 'test',
            'access_token' => 'test',
            'response_data_formatter' => TestResponseDataFormatter::class,
            'handler' => $handler
        ]);
        $api = ClientManager::get('test-response-data-formatter');
        $this->assertSame(TestResponseDataFormatter::class, $api->getOption('response_data_formatter'));

        $mock->append(
            new Response(200, [], '{"product": {"id": 1234567890}}')
        );
        $product = $api->get('products/1234567890');
        $this->assertInstanceOf(\stdClass::class, $product);
        $this->assertSame(1234567890, $product->product->id);
    }
}
