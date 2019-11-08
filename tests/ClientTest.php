<?php

namespace ShopifyAPI\Tests;

use DateTime;
use Exception;
use VladimirCatrici\Shopify\API;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use VladimirCatrici\Shopify\Client;
use VladimirCatrici\Shopify\ClientConfig;
use VladimirCatrici\Shopify\Exception\RequestException;
use function VladimirCatrici\Shopify\getOldestSupportedVersion;

class ClientTest extends TestCase {
    /**
     * @var MockHandler
     */
    private static $mock;
    /**
     * @var Client
     */
    private static $client;

    public static function setUpBeforeClass() {
        self::$mock = new MockHandler();
        $handler = HandlerStack::create(self::$mock);
        ClockMock::register(API::class);
        self::$client = new Client(new ClientConfig([
            'handle' => 'test-domain',
            'accessToken' => 'test-token',
            'maxAttemptsOnServerErrors' => 1,
            'maxAttemptsOnRateLimitErrors' => 10,
            'httpClientOptions' => [
                'handler' => $handler
            ]
        ]));
    }

    public function testConstructor() {
        $client = new Client(new ClientConfig([
            'handle' => 'test',
            'accessToken' => 'test',
            'maxAttemptsOnRateLimitErrors' => 5
        ]));
        $this->assertEquals(5, $client->getConfig()->getMaxAttemptsOnRateLimitErrors());
    }

    /**
     * @throws RequestException
     */
    public function testGet() {
        self::$mock->append(
            new Response(200, [], '{"products": [{"id": 1, "title": "Product 1"},{"id": 2, "title": "Product 2"}]}'),
            new Response(404)
        );

        // Check JSON decoding
        $products = self::$client->get('products', ['page' => 1, 'limit' => 250]);
        $this->assertEquals(self::$client->respCode, 200);
        $this->assertCount(2, $products);
        $this->assertEquals($products[0]['id'], 1);
        $this->assertEquals($products[1]['title'], 'Product 2');

        // Check exception
        $this->expectException(RequestException::class);
        self::$client->get('products');
    }

    /**
     * @throws RequestException
     */
    public function testPost() {
        self::$mock->append(
            new Response(201, [], '{"product": {"id": 1234567890, "title": "Test"}}')
        );
        $resp = self::$client->post('products', [
            'product' => [
                'title' => 'Test'
            ]
        ]);
        $this->assertEquals(201, self::$client->respCode);
        $this->assertEquals(1234567890, $resp['id']);
        $this->assertEquals('Test', $resp['title']);
    }

    /**
     * @throws RequestException
     */
    public function testPut() {
        self::$mock->append(
            new Response(200, [], '{"product": {"id": 1234567890, "title": "Test 2"}}')
        );
        $resp = self::$client->put('products/1234567890', [
            'product' => [
                'id' => '1234567890',
                'title' => 'Test 2'
            ]
        ]);
        $this->assertEquals(200, self::$client->respCode);
        $this->assertEquals(1234567890, $resp['id']);
        $this->assertEquals('Test 2', $resp['title']);
    }

    /**
     * @throws RequestException
     */
    public function testDelete() {
        self::$mock->append(new Response(200));
        $resp = self::$client->delete('products/1234567890');
        $this->assertEquals(200, self::$client->respCode);
        $this->assertEmpty($resp);
    }

    /**
     * @throws RequestException
     */
    public function testAttemptsOptions() {
        self::$client->getConfig()->setMaxAttemptsOnServerErrors(4);
        self::$mock->append(
            new Response(500),
            new Response(503),
            new Response(504),
            new Response(200)
        );
        self::$client->get('products');
        $this->assertEquals(self::$client->respCode, 200);
    }

    /**
     * @group time-sensitive
     * @throws RequestException
     */
    public function test429ResponseHandling() {
        self::$mock->append(
            new Response(429),
            new Response(200)
        );
        $start = time();
        self::$client->get('products');
        $this->assertEquals(1, time() - $start);
    }

    /**
     * @group time-sensitive
     * @throws RequestException
     */
    public function test429RetryAfterHandling() {
        self::$mock->append(
            new Response(429, ['Retry-After' => 2.0]),
            new Response(200)
        );
        $start = time();
        self::$client->get('products');
        $this->assertEquals(2, time() - $start);
    }

    /**
     * @group time-sensitive
     * @throws RequestException
     */
    public function testMaxRateLimitOption() {
        self::$mock->append(
            // The default `max_rate_limit` option is 0.5, so 21/40 exceeds the limit
            new Response(200, ['X-Shopify-Shop-Api-Call-Limit' => '21/40']),
            // The 20/40 is OK as equals to 0.5
            new Response(200, ['X-Shopify-Shop-Api-Call-Limit' => '20/40']),
            // Testing the changing the `max_rate_limit` option to 0.75
            new Response(200, ['X-Shopify-Shop-Api-Call-Limit' => '31/40']),
            // The 30/40 is OK as equals to 0.75
            new Response(200, ['X-Shopify-Shop-Api-Call-Limit' => '30/40'])
        );
        $start = time();
        self::$client->get('products');
        self::$client->get('products');
        $this->assertEquals(1, time() - $start); // It sleeps for 1 second by default

        $start = time();
        self::$client->getConfig()->setMaxLimitRate(0.75);
        self::$client->get('products');
        self::$client->get('products');
        $this->assertEquals(1, time() - $start);
    }

    /**
     * @group time-sensitive
     * @throws RequestException
     */
    public function testMaxRateLimitSleepSecOption() {
        self::$client->getConfig()
            ->setMaxLimitRate(0.25)
            ->setMaxLimitRateSleepSeconds(5);
        self::$mock->append(
            new Response(200, ['X-Shopify-Shop-Api-Call-Limit' => '21/80']),
            new Response(200, ['X-Shopify-Shop-Api-Call-Limit' => '20/80'])
        );
        $start = time();
        self::$client->get('products');
        self::$client->get('products');
        $this->assertEquals(5, time() - $start);
    }

    /**
     * @dataProvider invalidApiVersion
     * @param $invalidApiVersion API version to be provided by the provided invalidApiVersion
     */
    public function testThrowingAnExceptionOnPassingApiVersionInInvalidFormatToConstructor($invalidApiVersion) {
        $this->expectException(InvalidArgumentException::class);
        new Client(new ClientConfig([
            'api_version' => $invalidApiVersion
        ]));
    }

    /**
     * @dataProvider invalidApiVersion
     * @param $invalidApiVersion API version to be provided by the invalidApiVersion provider
     */
    public function testThrowingAnExceptionOnSettingApiVersionInInvalidFormatAfterInitialization($invalidApiVersion) {
        $api = new Client(new ClientConfig([
            'handle' => 'test',
            'accessToken' => 'test'
        ]));
        $this->expectException(InvalidArgumentException::class);
        $api->getConfig()->setApiVersion($invalidApiVersion);
    }

    /**
     * @dataProvider validApiVersion
     * @param $validApiVersion API version to be provided by the validApiVersion provider
     * @throws Exception
     */
    public function testSettingShopifyApiVersionWithConstructor($validApiVersion) {
        $api = new Client(new ClientConfig([
            'handle' => 'test',
            'accessToken' => 'test',
            'apiVersion' => $validApiVersion
        ]));
        $this->assertEquals($validApiVersion, $api->getConfig()->getApiVersion());
    }

    /**
     * @dataProvider  validApiVersion
     * @param $validApiVersion
     * @throws Exception
     */
    public function testSettingShopifyApiVersionAfterInitialization($validApiVersion) {
        $apiVersion = '2019-04';
        $mock = new MockHandler();
        $handler = HandlerStack::create($mock);
        $api = new Client(new ClientConfig([
            'handle' => 'test',
            'accessToken' => 'test',
            'apiVersion' => $apiVersion,
            'httpClientOptions' => [
                'handler' => $handler
            ]
        ]));
        $api->getConfig()->setApiVersion($validApiVersion);
        $this->assertEquals($validApiVersion, $api->getConfig()->getApiVersion());
        $mock->append(new Response(200, [], '{}'));
        $api->get('products');
    }

    /**
     * @throws Exception
     */
    public function testGettingShopifyApiVersionWithoutSettingItWithConstructor() {
        $mock = new MockHandler();
        $handler = HandlerStack::create($mock);
        $api = new Client(new ClientConfig([
            'handle' => 'test',
            'accessToken' => 'test',
            'httpClientOptions' => [
                'handler' => $handler
            ]
        ]));
        $apiVersion = '2019-07';
        $mock->append(
            new Response(200, ['X-Shopify-API-Version' => $apiVersion], '{"shop": {"id": 1234567890}}')
        );
        $this->assertEquals(getOldestSupportedVersion(), $api->getConfig()->getApiVersion());
        $api->get('shop');
        $this->assertEquals($apiVersion, $api->getConfig()->getApiVersion());
    }

    /**
     * @dataProvider oldestSupportedVersionDataProvider
     * @param $date
     * @param $apiVersionExpected
     * @throws Exception
     */
    public function testChoosingCorrectOldestSupportedApiVersion($date, $apiVersionExpected) {
        $this->assertEquals($apiVersionExpected, getOldestSupportedVersion($date));
    }

    public function testSetConfig() {
        $client = new Client(new ClientConfig([
            'handle' => 'test-handle',
            'accessToken' => 'test-access-token'
        ]));
        $config = $client->getConfig();
        $newHandle = 'new-handle';
        $newAccessToken = 'new-access-token';
        $newDomain = $newHandle . '.myshopify.com';
        $newBaseURL = 'https://' . $newDomain . '/admin/';
        $config->setHandle('new-handle')
            ->setAccessToken('new-access-token');
        $client->setConfig($config);
        $this->assertSame($newHandle, $client->getConfig()->getHandle());
        $this->assertSame($newAccessToken, $client->getConfig()->getAccessToken());
        $this->assertSame($newDomain, $client->getConfig()->getPermanentDomain());
        $this->assertSame($newBaseURL, $client->getConfig()->getBaseUrl());
        $this->assertSame(getOldestSupportedVersion(), $client->getConfig()->getApiVersion());

        $latestStableVersion = getOldestSupportedVersion((new DateTime())->modify('+1 year'));
        $config->setApiVersion($latestStableVersion);
        $client->setConfig($config);
        $this->assertSame($latestStableVersion, $client->getConfig()->getApiVersion());
    }

    /**
     * The Client depends on GuzzleHttpClient and its options like base URL and access token. As these configuration
     * options can be changed at any time, we also need to reset Guzzle HTTP Client every time this happens.
     */
    public function testClientIsReinitializedOnceSensitiveConfigOptionChanged() {
        $mock = new MockHandler();
        $handler = HandlerStack::create($mock);
        $config = new ClientConfig([
            'handle' => 'test-handle',
            'accessToken' => 'test-access-token',
            'httpClientOptions' => [
                'handler' => $handler
            ]
        ]);
        $mock->append(
            new Response(200, [], '{}'),
            new Response(200, [], '{}'),
            new Response(200, [], '{}'),
            new Response(200, [], '{}')
        );
        $client = new ClientTestDouble($config);
        $this->assertSame('test-handle.myshopify.com', $client->getConfig()->getPermanentDomain());
        $config->setHandle('test-handle-2');
        $client->get('blah');
        $guzzleClient = $client->getGuzzleClient();
        $guzzleConfig = $guzzleClient->getConfig();
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertSame('test-handle-2.myshopify.com', $guzzleConfig['base_uri']->getHost());

        $config->setAccessToken('new-access-token');
        // Changing sensitive data in the config trigger Guzzle HTTP Client reset only on request
        $client->get('blah');
        $guzzleClient = $client->getGuzzleClient();
        $guzzleConfig = $guzzleClient->getConfig();
        $this->assertSame('new-access-token', $guzzleConfig['headers']['X-Shopify-Access-Token']);

        $config->setHttpClientOptions([
            'handler' => $handler,
            'foo' => 'bar'
        ]);
        $client->get('blah');
        $guzzleClient = $client->getGuzzleClient();
        $guzzleConfig = $guzzleClient->getConfig();
        $this->assertArrayHasKey('foo', $guzzleConfig);
        $this->assertSame('bar', $guzzleConfig['foo']);

        $this->assertSame(getOldestSupportedVersion(), $client->getConfig()->getApiVersion());
        $this->assertNotContains('api', $guzzleConfig['base_uri']->getPath());
        $client->getConfig()->setApiVersion('2019-10');
        $client->get('products');
        $guzzleClient = $client->getGuzzleClient();
        $guzzleConfig = $guzzleClient->getConfig();
        $this->assertContains('api', $guzzleConfig['base_uri']->getPath());
        $this->assertContains('api/2019-10', $guzzleConfig['base_uri']->getPath());
    }

    /**
     * @return array
     * @throws Exception
     */
    public function oldestSupportedVersionDataProvider() {
        return [
            ['2019-08', '2019-04'],
            ['2019-09-01', '2019-04'],
            ['2019-10-26 00:00:00', '2019-04'],
            ['2019-11-15 23:59:59', '2019-04'],
            ['2019-08', '2019-04'],
            ['2019-08', '2019-04'],
            ['2019-08', '2019-04'],
            [new DateTime('2020-01-01'), '2019-04'],
            ['2020-03-31 23:59:59', '2019-04'],
            ['2020-04-01 00:00:00', '2019-07'],
            ['2020-04', '2019-07'],
            ['2020-06', '2019-07'],
            ['2020-07', '2019-10'],
            ['2020-09', '2019-10'],
            ['2020-10', '2020-01']
        ];
    }

    /**
     * @return array A list of API versions in invalid format
     */
    public function invalidApiVersion() {
        return [
            ['2019'],
            [2020],
            ['2018-04'],
            ['2019-4'],
            ['20-01'],
            ['2019-00'],
            ['2020-02'],
            ['2020-03'],
            ['2020-05'],
            ['2020-06'],
            ['2020-08'],
            ['2020-09'],
            ['2020-11'],
            ['2020-12'],
            ['2019-13']
        ];
    }

    /**
     * @return array A list of API version in a valid (not real valid API versions)
     */
    public function validApiVersion() {
        return [
            ['2019-04'],
            ['2019-07'],
            ['2019-10'],
            ['2020-01'],
            ['2020-04'],
            ['2020-07'],
            ['2020-10'],
            ['2021-01']
        ];
    }
}