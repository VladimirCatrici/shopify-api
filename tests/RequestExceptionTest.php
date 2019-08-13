<?php
namespace ShopifyAPI\Tests;

use VladimirCatrici\Shopify\API;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;

class RequestExceptionTest extends TestCase {
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
            'max_attempts_on_rate_limit_errors' => 1
        ]);
    }

    /**
     * @throws GuzzleException
     */
    public function testGetResponse() {
        self::$mock->append(new Response(500));
        try {
            self::$api->get('products');
        } catch (API\RequestException $e) {
            $this->assertInstanceOf(Response::class, $e->getResponse());
        }
    }

    /**
     * @throws GuzzleException
     */
    public function testGetDetailsJson() {
        self::$mock->append(new Response(500, ['X-Test-Header' => '12345'], 'Test Body'));
        try {
            self::$api->get('products');
        } catch (API\RequestException $e) {
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
}