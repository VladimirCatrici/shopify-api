<?php

namespace ShopifyAPI\Tests;

use VladimirCatrici\Shopify\API;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use VladimirCatrici\Shopify\Exception\RequestException;

class RequestExceptionTest extends TestCase
{
    /**
     * @var MockHandler
     */
    private static $mock;
    /**
     * @var API
     */
    private static $api;

    public static function setUpBeforeClass()
    {
        self::$mock = new MockHandler();
        $handler = HandlerStack::create(self::$mock);
        ClockMock::register(API::class);
        self::$api = new API('test-domain', 'test-token', [
            'handler' => $handler,
            'max_attempts_on_server_errors' => 1,
            'max_attempts_on_rate_limit_errors' => 1
        ]);
    }

    public function testGetResponse()
    {
        self::$mock->append(new Response(500));
        try {
            self::$api->get('products');
        } catch (RequestException $e) {
            $this->assertInstanceOf(Response::class, $e->getResponse());
        }
    }

    public function testGetDetailsJsonOnGetRequest()
    {
        self::$mock->append(new Response(500, ['X-Test-Header' => '12345'], 'Test Body'));
        try {
            self::$api->get('products', [
                'limit' => 10,
                'test-option' => 'test-value'
            ]);
        } catch (RequestException $e) {
            $detailsJson = $e->getDetailsJson();
            $this->assertJson($detailsJson);
            $detailsArr = json_decode($detailsJson, true, 512);
            $this->assertThat(
                $detailsArr,
                $this->logicalAnd(
                    $this->arrayHasKey('msg'),
                    $this->arrayHasKey('request'),
                    $this->arrayHasKey('response')
                )
            );
            // Test request details
            $this->assertInternalType('array', $detailsArr['request']);
            $this->assertArrayHasKey('method', $detailsArr['request']);
            $this->assertSame('GET', $detailsArr['request']['method']);
            $this->assertSame('test-domain.myshopify.com', $detailsArr['request']['uri']['host']);
            $this->assertSame('https', $detailsArr['request']['uri']['scheme']);
            $this->assertSame('/admin/products.json', $detailsArr['request']['uri']['path']);
            $this->assertSame('limit=10&test-option=test-value', $detailsArr['request']['uri']['query']);

            // Test response details
            $this->assertInternalType('array', $detailsArr['response']);
            $this->assertThat(
                $detailsArr['response'],
                $this->logicalAnd(
                    $this->arrayHasKey('code'),
                    $this->arrayHasKey('body'),
                    $this->arrayHasKey('headers')
                )
            );
            $this->assertEquals(500, $detailsArr['response']['code']);
            $this->assertEquals('Test Body', $detailsArr['response']['body']);
            $this->assertInternalType('array', $detailsArr['response']['headers']);
            $this->assertArrayHasKey('X-Test-Header', $detailsArr['response']['headers']);
            $this->assertEquals('12345', $detailsArr['response']['headers']['X-Test-Header'][0]);
        }
    }

    public function testGetDetailsJsonOnPostRequest()
    {
        self::$mock->append(new Response(500, ['X-Test-Header' => '12345'], 'Test Internal Server Error'));
        try {
            $postDataArr = [
                'product' => [
                    'title' => 'Test product title'
                ]
            ];
            self::$api->post('products', $postDataArr);
        } catch (RequestException $e) {
            $detailsJson = $e->getDetailsJson();
            $this->assertJson($detailsJson);
            $detailsArr = json_decode($detailsJson, true, 512);
            $this->assertThat(
                $detailsArr,
                $this->logicalAnd(
                    $this->arrayHasKey('msg'),
                    $this->arrayHasKey('request'),
                    $this->arrayHasKey('response')
                )
            );
            // Test request details
            $this->assertInternalType('array', $detailsArr['request']);
            $this->assertArrayHasKey('method', $detailsArr['request']);
            $this->assertSame('POST', $detailsArr['request']['method']);
            $this->assertSame('test-domain.myshopify.com', $detailsArr['request']['uri']['host']);
            $this->assertSame('https', $detailsArr['request']['uri']['scheme']);
            $this->assertSame('/admin/products.json', $detailsArr['request']['uri']['path']);
            $this->assertEmpty($detailsArr['request']['uri']['query']);
            $this->assertSame(json_encode($postDataArr), $detailsArr['request']['body']);

            // Test response details
            $this->assertInternalType('array', $detailsArr['response']);
            $this->assertThat(
                $detailsArr['response'],
                $this->logicalAnd(
                    $this->arrayHasKey('code'),
                    $this->arrayHasKey('body'),
                    $this->arrayHasKey('headers')
                )
            );
            $this->assertEquals(500, $detailsArr['response']['code']);
            $this->assertEquals('Test Internal Server Error', $detailsArr['response']['body']);
            $this->assertInternalType('array', $detailsArr['response']['headers']);
            $this->assertArrayHasKey('X-Test-Header', $detailsArr['response']['headers']);
            $this->assertEquals('12345', $detailsArr['response']['headers']['X-Test-Header'][0]);
        }
    }
}
