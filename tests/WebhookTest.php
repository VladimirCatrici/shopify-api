<?php

namespace ShopifyAPI\Tests;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use VladimirCatrici\Shopify\Webhook;

class WebhookTest extends TestCase {
    private static $pid;
    /**
     * @var Client
     */
    private static $client;
    private $requestBody = [];
    private $requestOptions = [];
    private $webhookToken = 'S%do$Eq5PawfdnA%chEGRcj8Q@ANPA2h';

    public static function setUpBeforeClass() {
        // Command to start built-in PHP server.
        $cmd = 'php -S localhost:7777 ' . dirname(__FILE__) . '/webhook-processor.php';

        // It depends on OS how to run it in the background.
        if (substr(php_uname(), 0, 7) == "Windows") {
            self::$pid = proc_open($cmd, [], $pipes);
        } else {
            self::$pid = exec($cmd . " > /dev/null &");
        }

        sleep(1); // Give the server some time to start

        self::$client = new Client();
    }

    public static function tearDownAfterClass() {
        if (substr(php_uname(), 0, 7) == "Windows") {
            // Kill the child process (built-in PHP server) started with the specified port
            exec('netstat -ano | findstr :7777', $output);
            foreach ($output as $line) {
                preg_match('/\d+$/', $line, $matches);
                $pid = $matches[0];
                if (!empty($pid)) {
                    exec('taskkill /F /PID ' . $pid, $killOutput);
                    foreach ($killOutput as $str) {
                        echo $str . "\n";
                    }
                }
            }

            // Kill the process initiated built-in server start
            $status = proc_get_status(self::$pid);
            proc_close(self::$pid);
            echo sprintf('The parent process PID %d has been terminated', $status['pid']);
        } else {
            // Kill the child process (built-in PHP server) started with the specified port
            exec('netstat -ano | findstr :7777', $output);
            foreach ($output as $line) {
                preg_match('/\d+$/', $line, $matches);
                $pid = $matches[0];
                if (!empty($pid)) {
                    exec('kill -9 ' . $pid, $killOutput);
                    foreach ($killOutput as $str) {
                        echo $str . "\n";
                    }
                }
            }

            // Kill the process initiated built-in server start
            exec('kill -9 ' . self::$pid);
            echo sprintf('The parent process PID %d has been terminated', self::$pid);
        }
    }

    public function setUp() {
        $this->requestBody = ['id' => 1234567890]; // that's enough to test webhook
        $bodyJson = json_encode($this->requestBody);
        $signature = base64_encode(hash_hmac('sha256', $bodyJson, $this->webhookToken, true));
        $this->requestOptions = [
            'body' => $bodyJson,
            'headers' => [
                'X-Shopify-Topic' => 'orders/create',
                'X-Shopify-Shop-Domain' => 'test.myshopify.com',
                'X-Shopify-API-Version' => '2010-10',
                'X-Shopify-Hmac-Sha256' => $signature
            ]
        ];

        // Setup HTTP headers into the global $_SERVER variable to be able test Webhook class using a test double
        foreach ($this->requestOptions['headers'] as $key => $val) {
            $_SERVER['HTTP_' . str_replace('-', '_', mb_strtoupper($key))] = $val;
        }
    }

    public function tearDown() {
        WebhookTestDouble::clearData();
    }

    public function testWebhookSendingToThePhpBuiltInServer() {
        /** @noinspection PhpUnhandledExceptionInspection */
        $response = self::$client->request('POST', 'http://localhost:7777', $this->requestOptions);
        $this->assertEquals(200, $response->getStatusCode());
        $respContent = $response->getBody()->getContents();
        $this->assertJson($respContent);
        $resp = json_decode($respContent, true);
        return $resp;
    }

    /**
     * @depends testWebhookSendingToThePhpBuiltInServer
     * @param $resp
     */
    public function testHeadersParsing($resp) {
        $this->assertEquals($this->requestOptions['headers']['X-Shopify-Topic'], $resp['topic']);
        $this->assertEquals($this->requestOptions['headers']['X-Shopify-Shop-Domain'], $resp['shop_domain']);
        $this->assertEquals($this->requestOptions['headers']['X-Shopify-API-Version'], $resp['api_version']);

        $this->assertEquals($this->requestOptions['headers']['X-Shopify-Topic'], Webhook::getTopic());
        $this->assertEquals($this->requestOptions['headers']['X-Shopify-Shop-Domain'], Webhook::getShopDomain());
        $this->assertEquals($this->requestOptions['headers']['X-Shopify-API-Version'], Webhook::getApiVersion());
    }

    public function testMissingHeadersReturnEmptyStrings() {
        unset($this->requestOptions['headers']['X-Shopify-Topic']);
        unset($this->requestOptions['headers']['X-Shopify-Shop-Domain']);
        unset($this->requestOptions['headers']['X-Shopify-API-Version']);
        $resp = $this->sendWebhook();
        $this->assertEmpty($resp['topic']);
        $this->assertEmpty($resp['shop_domain']);
        $this->assertEmpty($resp['api_version']);
    }

    /**
     * @depends testWebhookSendingToThePhpBuiltInServer
     * @param $resp
     */
    public function testDataParsing($resp) {
        $this->assertEquals($this->requestBody['id'], json_decode($resp['body'], true, 512)['id']);
        $this->assertInternalType('array', $resp['data_arr']);
        $this->assertEquals($this->requestBody, $resp['data_arr']);
    }

    public function testDoubleGetData() {
        WebhookTestDouble::setInputStream($this->requestOptions['body']);
        $this->assertSame($this->requestOptions['body'], WebhookTestDouble::getData());
        $this->assertSame($this->requestBody, WebhookTestDouble::getDataAsArray());
    }

    /**
     * @depends testWebhookSendingToThePhpBuiltInServer
     * @param $resp
     */
    public function testPassingValidationWhenEverythingIsOk($resp) {
        $this->assertTrue($resp['validation']);
    }

    public function testDoublePassingValidationWhenEverythingIsOk() {
        WebhookTestDouble::setInputStream($this->requestOptions['body']);
        $this->assertTrue(WebhookTestDouble::validate($this->webhookToken));
    }

    /**
     * Test webhook validation returns FALSE if data is corrupted.
     */
    public function testWebhookValidationFailsIfBodyContentIsCorrupted() {
        /*
         * Built-in PHP server implementation.
         */
        $this->requestOptions['body'] = '{}';
        $resp = $this->sendWebhook();
        $this->assertFalse($resp['validation']);

        /*
         * Let's also test it with directly to have better coverage. We can use Webhook class here instead of
         * WebhookTestDouble because it uses php://input stream to get data which will be empty on running this test.
         */
        $this->assertFalse(Webhook::validate($this->webhookToken));
    }

    public function testWebhookValidationFailsIfWebhookTokenIsWrong() {
        $bodyJson = $this->requestOptions['body'];
        $webhookToken = 'S%do$Eq5PawfdnA%chEGRcj8Q@ANPA2k';
        $signature = base64_encode(hash_hmac('sha256', $bodyJson, $webhookToken, true));
        $this->requestOptions['headers']['X-Shopify-Hmac-Sha256'] = $signature;
        $resp = $this->sendWebhook();
        $this->assertFalse($resp['validation']);
    }

    /**
     * Sends webhook with data from the $this->>requestOptions. This data should be prepared before calling this method.
     * For testing purposes we're using `webhook-processor.php` script that is listening for webhook and returns data
     * that was parsed by Webhook class in a JSON format.
     * @noinspection PhpDocMissingThrowsInspection
     * @return array
     */
    private function sendWebhook() : array {
        /** @noinspection PhpUnhandledExceptionInspection */
        $response = self::$client->request('POST', 'http://localhost:7777', $this->requestOptions);
        $respContent = $response->getBody()->getContents();
        return json_decode($respContent, true);
    }
}
