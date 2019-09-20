<?php


namespace ShopifyAPI\Test;

use Exception;
use VladimirCatrici\Shopify\API;
use PHPUnit\Framework\TestCase;
use VladimirCatrici\Shopify\Collection;
use VladimirCatrici\Shopify\Exception\RequestException;

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
     * @throws RequestException
     */
    public function testCount() {
        $productsCount = self::$api->get('products/count');
        $this->assertInternalType('integer', $productsCount);
    }

    /**
     * @throws RequestException
     */
    public function testGet() {
        $products = self::$api->get('products');
        $this->assertInternalType('array', $products);
        $this->assertCount(0, $products);
    }

    /**
     * @return int
     * @throws RequestException
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
     * @param int $productId
     * @return int
     * @throws RequestException
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
     * @throws RequestException
     */
    public function testDelete(int $productId) {
        self::$api->delete('products/' . $productId);
        $this->assertEquals(200, self::$api->respCode);

        // Check the product is actually deleted
        $this->expectException(RequestException::class);
        self::$api->get('products/' . $productId);
        $this->assertEquals(404, self::$api->respCode);
    }

    public function testNotFoundRequestException() {
        try {
            self::$api->get('products/1');
        } catch (RequestException $e) {
            $json = $e->getDetailsJson();
            $arr = json_decode($json, true, 512);
            $this->assertEquals(404, $arr['response']['code']);
            $this->assertEquals('{"errors":"Not Found"}', $arr['response']['body']);
        }
    }

    /**
     * @throws RequestException
     * @throws Exception
     */
    public function testChangingApiVersion() {
        self::$api->get('products/count');
        $apiVersion = self::$api->getVersion();
        $regex = '/^(\d{4})-(\d{2})$/';
        $this->assertRegExp($regex, $apiVersion);

        $nextApiVersion = $this->getNextApiVersion($apiVersion);
        self::$api->setVersion($nextApiVersion);
        self::$api->get('products/count');
        $this->assertEquals($nextApiVersion, self::$api->respHeaders['X-Shopify-API-Version'][0]);

        $nextApiVersion = $this->getNextApiVersion($nextApiVersion);
        self::$api->setVersion($nextApiVersion);
        self::$api->get('products/count');
        $this->assertEquals($nextApiVersion, self::$api->respHeaders['X-Shopify-API-Version'][0]);
    }

    /**
     * @throws RequestException
     */
    public function testCollection() {
        // Create test products
        $createdProductIds = [];
        for ($i = 1; $i < 6; $i++) {
            $title = 'Product ' . $i;
            $variant = [
                'price' => mt_rand(0, 99) . '.' . mt_rand(0, 99),
                'sku' => 'TEST' . $i
            ];
            $resp = self::$api->post('products', [
                'product' => [
                    'title' => $title,
                    'variants' => [$variant]
                ]
            ]);
            $respVariant = $resp['variants'][0];
            $createdProductIds[] = $resp['id'];
            $this->assertEquals(201, self::$api->respCode);
            $this->assertEquals($title, $resp['title']);
            $this->assertEquals($variant['price'], $respVariant['price']);
            $this->assertEquals($variant['sku'], $respVariant['sku']);
        }

        $newCount = self::$api->get('products/count');
        $products = new Collection(self::$api, 'products');
        $this->assertEquals($newCount, count($products));
        $this->assertEquals($newCount, $products->count());
        $iterationCount = 0;
        foreach ($products as $product) {
            $this->assertInternalType('array', $product);
            $iterationCount++;
        }
        $this->assertEquals($newCount, $iterationCount);

        // Delete created variants
        foreach ($createdProductIds as $id) {
            self::$api->delete('products/' . $id);
            $this->assertEquals(200, self::$api->respCode);
        }

        $newCount = self::$api->get('products/count');
        $this->assertEquals(0, $newCount);
        $products = new Collection(self::$api, 'products');
        $this->assertEquals($newCount, count($products));
        $this->assertEquals($newCount, $products->count());
        $iterationCount = 0;
        foreach ($products as $product) {
            $this->assertInternalType('array', $product);
            $iterationCount++;
        }
        $this->assertEquals($newCount, $iterationCount);
    }

    private function getNextApiVersion($currentApiVersion) {
        $regex = '/^(\d{4})-(\d{2})$/';
        preg_match($regex, $currentApiVersion, $matches);
        if ($matches[2] == '01') {
            $nextApiVersion = $matches[1] . '-04';
        } elseif ($matches[2] == '04') {
            $nextApiVersion = $matches[1] . '-07';
        } elseif ($matches[2] == '07') {
            $nextApiVersion = $matches[1] . '-10';
        } else {
            $nextApiVersion = ($matches[1] + 1) . '-01';
        }
        return $nextApiVersion;
    }
}