<?php


namespace ShopifyAPI\Test;

use VladimirCatrici\Shopify\API;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\TestCase;

class ShopifyApiOnlineTest extends TestCase {
    /**
     * @var API
     */
    public static $api;

    private $demoProduct = [
        'title' => 'Test product',
        'body_html' => '<p>Test product content</p>',
        'vendor' => 'CyberDev',
        'product_type' => 'Electronics',
        'tags' => 'test 1, test 2',
        'variants' => [
            [
                'price' => 9.99,
                'option1' => 'First',
                'sku' => 'TEST_FIRST'
            ],
            [
                'price' => 0.01,
                'option1' => 'Second',
                'sku' => 'TEST_SECOND'
            ]
        ]
    ];

    public static function setUpBeforeClass() {
        self::$api = new API(getenv('SHOPIFY_API_DOMAIN'), getenv('SHOPIFY_API_PASSWORD'), [
            'max_attempts_on_server_errors' => 3,
            'max_attempts_on_rate_limit_errors' => 10
        ]);
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testCount() {
        $productsCount = self::$api->get('products/count');
        $this->assertInternalType('integer', $productsCount);
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testGet() {
        $products = self::$api->get('products');
        $this->assertInternalType('array', $products);
        $this->assertCount(0, $products);
    }

    /**
     * @return int
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testPost() {
        $response = self::$api->post('products', [
            'product' => $this->demoProduct
        ]);
        $this->assertEquals(201, self::$api->respCode);
        $this->assertInternalType('array', $response);
        $this->assertArrayHasKey('id', $response);
        $this->assertEquals($this->demoProduct['title'], $response['title']);
        $this->assertEquals($this->demoProduct['body_html'], $response['body_html']);
        $this->assertEquals($this->demoProduct['vendor'], $response['vendor']);
        $this->assertEquals($this->demoProduct['product_type'], $response['product_type']);
        $this->assertEquals($this->demoProduct['tags'], $response['tags']);
        $this->assertArrayHasKey('variants', $response);
        $this->assertCount(count($this->demoProduct['variants']), $response['variants']);
        $this->assertEquals($this->demoProduct['variants'][0]['price'], $response['variants'][0]['price']);
        $this->assertEquals($this->demoProduct['variants'][0]['option1'], $response['variants'][0]['option1']);
        $this->assertEquals($this->demoProduct['variants'][0]['sku'], $response['variants'][0]['sku']);
        $this->assertEquals($this->demoProduct['variants'][1]['price'], $response['variants'][1]['price']);
        $this->assertEquals($this->demoProduct['variants'][1]['option1'], $response['variants'][1]['option1']);
        $this->assertEquals($this->demoProduct['variants'][1]['sku'], $response['variants'][1]['sku']);

        return $response['id'];
    }

    /**
     * @depends testPost
     * @param $productId
     * @return int
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testPut(int $productId) {
        $newProductTitle = 'New product title';
        $response = self::$api->put('products/' . $productId, [
            'product' => [
                'id' => $productId,
                'title' => $newProductTitle
            ]
        ]);
        $this->assertEquals(200, self::$api->respCode);
        $this->assertInternalType('array', $response);
        $this->assertArrayHasKey('title', $response);
        $this->assertEquals($newProductTitle, $response['title']);

        return $response['id'];
    }

    /**
     * @depends testPost
     * @param int $productId
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testDelete(int $productId) {
        self::$api->delete('products/' . $productId);
        $this->assertEquals(200, self::$api->respCode);

        // Check the product is actually deleted
        $this->expectException(API\RequestException::class);
        self::$api->get('products/' . $productId);
        $this->assertEquals(404, self::$api->respCode);
    }

    /**
     * @throws GuzzleException
     */
    public function testNotFoundRequestException() {
        try {
            self::$api->get('products/1');
        } catch (API\RequestException $e) {
            $json = $e->getDetailsJson();
            $arr = json_decode($json, true, 512);
            $this->assertEquals(404, $arr['response']['code']);
            $this->assertEquals('{"errors":"Not Found"}', $arr['response']['body']);
        }
    }
}