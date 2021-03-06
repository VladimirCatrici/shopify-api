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
use VladimirCatrici\Shopify\Exception\RequestException;

class ShopifyApiTest extends TestCase {
    /**
     * @var MockHandler
     */
    private static $mock;
    /**
     * @var API
     */
    private static $api;

    public static function setUpBeforeClass() {
        self::$mock = new MockHandler();
        $handler = HandlerStack::create(self::$mock);
        ClockMock::register(API::class);
        self::$api = new API('test-domain', 'test-token', [
            'handler' => $handler,
            'max_attempts_on_server_errors' => 1,
            'max_attempts_on_rate_limit_errors' => 10
        ]);
    }

    public function testConstructor() {
        $api = new API('test', 'test', [
            'max_attempts_on_rate_limit_errors' => 5
        ]);
        $this->assertEquals(5, $api->getOption('max_attempts_on_rate_limit_errors'));
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
        $products = self::$api->get('products', ['page' => 1, 'limit' => 250]);
        $this->assertEquals(self::$api->respCode, 200);
        $this->assertCount(2, $products);
        $this->assertEquals($products[0]['id'], 1);
        $this->assertEquals($products[1]['title'], 'Product 2');

        // Check exception
        $this->expectException(RequestException::class);
        self::$api->get('products');
    }

    /**
     * @throws RequestException
     */
    public function testPost() {
        self::$mock->append(
            new Response(201, [], '{"product": {"id": 1234567890, "title": "Test"}}')
        );
        $resp = self::$api->post('products', [
            'product' => [
                'title' => 'Test'
            ]
        ]);
        $this->assertEquals(201, self::$api->respCode);
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
        $resp = self::$api->put('products/1234567890', [
            'product' => [
                'id' => '1234567890',
                'title' => 'Test 2'
            ]
        ]);
        $this->assertEquals(200, self::$api->respCode);
        $this->assertEquals(1234567890, $resp['id']);
        $this->assertEquals('Test 2', $resp['title']);
    }

    /**
     * @throws RequestException
     */
    public function testDelete() {
        self::$mock->append(new Response(200));
        $resp = self::$api->delete('products/1234567890');
        $this->assertEquals(200, self::$api->respCode);
        $this->assertEmpty($resp);
    }

    public function testGetOption() {
        // Valid option
        $this->assertEquals(self::$api->getOption('max_attempts_on_server_errors'), 1);

        // Invalid option
        $this->expectException(InvalidArgumentException::class);
        self::$api->getOption('invalid_option_key');
    }

    public function testSetOption() {
        // Valid option
        self::$api->setOption('max_attempts_on_server_errors', 3);
        $this->assertEquals(self::$api->getOption('max_attempts_on_server_errors'), 3);

        // Invalid option
        $this->expectException(InvalidArgumentException::class);
        self::$api->setOption('invalid_option_key', true);
    }

    /**
     * @throws RequestException
     */
    public function testAttemptsOptions() {
        self::$api->setOption('max_attempts_on_server_errors', 4);
        self::$mock->append(
            new Response(500),
            new Response(503),
            new Response(504),
            new Response(200)
        );
        self::$api->get('products');
        $this->assertEquals(self::$api->respCode, 200);
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
        self::$api->get('products');
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
        self::$api->get('products');
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
        self::$api->get('products');
        self::$api->get('products');
        $this->assertEquals(1, time() - $start); // It sleeps for 1 second by default

        $start = time();
        self::$api->setOption('max_limit_rate', 0.75);
        self::$api->get('products');
        self::$api->get('products');
        $this->assertEquals(1, time() - $start);
    }

    /**
     * @group time-sensitive
     * @throws RequestException
     */
    public function testMaxRateLimitSleepSecOption() {
        self::$api->setOption('max_limit_rate', 0.25);
        self::$api->setOption('max_limit_rate_sleep_sec', 5);
        self::$mock->append(
            new Response(200, ['X-Shopify-Shop-Api-Call-Limit' => '21/80']),
            new Response(200, ['X-Shopify-Shop-Api-Call-Limit' => '20/80'])
        );
        $start = time();
        self::$api->get('products');
        self::$api->get('products');
        $this->assertEquals(5, time() - $start);
    }

    /**
     * @dataProvider invalidApiVersion
     * @param $invalidApiVersion API version to be provided by the provided invalidApiVersion
     */
    public function testThrowingAnExceptionOnPassingApiVersionInInvalidFormatToConstructor($invalidApiVersion) {
        $this->expectException(InvalidArgumentException::class);
        new API('test', 'test', [
            'api_version' => $invalidApiVersion
        ]);
    }

    /**
     * @dataProvider invalidApiVersion
     * @param $invalidApiVersion API version to be provided by the invalidApiVersion provider
     */
    public function testThrowingAnExceptionOnSettingApiVersionInInvalidFormatAfterInitialization($invalidApiVersion) {
        $api = new API('test', 'test');
        $this->expectException(InvalidArgumentException::class);
        $api->setVersion($invalidApiVersion);
    }

    /**
     * @dataProvider validApiVersion
     * @param $validApiVersion API version to be provided by the validApiVersion provider
     * @throws Exception
     */
    public function testSettingShopifyApiVersionWithConstructor($validApiVersion) {
        $api = new API('test', 'test', [
            'api_version' => $validApiVersion
        ]);
        $this->assertEquals($validApiVersion, $api->getVersion());
    }

    /**
     * @dataProvider  validApiVersion
     * @param $validApiVersion
     * @throws Exception
     */
    public function testSettingShopifyApiVersionAfterInitialization($validApiVersion) {
        $apiVersion = '2019-04';
        $api = new API('test', 'test', ['api_version' => $apiVersion]);
        $api->setVersion($validApiVersion);
        $this->assertEquals($validApiVersion, $api->getVersion());
    }

    /**
     * @throws Exception
     */
    public function testGettingShopifyApiVersionWithoutSettingItWithConstructor() {
        $mock = new MockHandler();
        $handler = HandlerStack::create($mock);
        $api = new API('test', 'test', [
            'handler' => $handler
        ]);
        $apiVersion = API::getOldestSupportedVersion();
        $mock->append(
            new Response(200, ['X-Shopify-API-Version' => $apiVersion], '{"shop": {"id": 1234567890}}')
        );
        $this->assertEquals($apiVersion, $api->getVersion());
        $this->assertEquals($apiVersion, $api->getOption('api_version'));
    }

    /**
     * @throws Exception
     */
    public function testSettingApiVersionViaSetOption() {
        $api = new API('test', 'test');
        $api->setOption('api_version', '2019-10');
        $this->assertEquals('2019-10', $api->getVersion());
    }

    /**
     * @dataProvider oldestSupportedVersionDataProvider
     * @param $date
     * @param $apiVersionExpected
     * @throws Exception
     */
    public function testChoosingCorrectOldestSupportedApiVersion($date, $apiVersionExpected) {
        $this->assertEquals($apiVersionExpected, self::$api::getOldestSupportedVersion($date));
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