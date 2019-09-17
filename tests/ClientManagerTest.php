<?php


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
}
