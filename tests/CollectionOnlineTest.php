<?php


namespace ShopifyAPI\Test;

use GuzzleHttp\Exception\GuzzleException;
use VladimirCatrici\Shopify\API;
use PHPUnit\Framework\TestCase;
use VladimirCatrici\Shopify\Collection;

class CollectionOnlineTest extends TestCase {
    /**
     * @var API
     */
    public static $api;

    private static $listOfCreatedProducts = [];

    public static function setUpBeforeClass() {
        self::$api = new API(getenv('SHOPIFY_API_DOMAIN'), getenv('SHOPIFY_API_PASSWORD'), [
            'max_attempts_on_server_errors' => 3,
            'max_attempts_on_rate_limit_errors' => 10
        ]);
    }

    public static function tearDownAfterClass() {
        if (!empty(self::$listOfCreatedProducts)) {
            foreach (self::$listOfCreatedProducts as $id) {
                self::$api->delete('products/' . $id);
                self::assertEquals(200, self::$api->respCode);
            }
        }
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testIteration() {
        $count = self::$api->get('products/count');
        if ($count > 0) {
            trigger_error('Store has products, exit.', E_USER_ERROR);
        }

        // Create products
        for ($i = 1; $i < 6; $i++) {
            $title = 'Product ' . $i;
            $variant = [
                'price' => mt_rand(0, 99) . '.', mt_rand(0, 99),
                'sku' => 'TEST' . $i
            ];
            $product = self::$api->post('products', [
                'product' => [
                    'title' => $title,
                    'variants' => [$variant]
                ]
            ]);
            $this->assertEquals(201, self::$api->respCode);
            self::$listOfCreatedProducts[] = $product['id'];
        }
        $numCreatedProducts = count(self::$listOfCreatedProducts);

        $products = new Collection(self::$api, 'products');
        $this->assertEquals($numCreatedProducts, count($products));
        $iterationCount = 0;
        foreach ($products as $p) {
            if (!empty($p)) {
                $iterationCount++;
            }
        }
        $this->assertEquals($numCreatedProducts, $iterationCount);

        $products2 = new Collection(self::$api, 'products', ['limit' => 2]);
        $this->assertEquals($numCreatedProducts, count($products));
        $iterationCount = 0;
        foreach ($products2 as $p) {
            if (!empty($p)) {
                $iterationCount++;
            }
        }
        $this->assertEquals($numCreatedProducts, $iterationCount);
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testCount() {
        $metafieldsToAdd = [
            [
                'namespace' => 'cws',
                'key' => 'testkey',
                'value' => 1,
                'value_type' => 'integer'
            ],
            [
                'namespace' => 'cws',
                'key' => 'testkey2',
                'value' => 2,
                'value_type' => 'string'
            ]
        ];
        $resp = self::$api->post('products', [
            'product' => [
                'title' => 'Test',
                'metafields' => $metafieldsToAdd
            ],
        ]);
        self::$api->setVersion('2019-07');
        $metafields = new Collection(self::$api, 'products/' . $resp['id'] . '/metafields');
        $numMetafields = count($metafields);
        $this->assertEquals(count($metafieldsToAdd), $numMetafields);
        $iterationCount = 0;
        foreach ($metafields as $key => $m) {
            $iterationCount++;
            $this->assertEquals($metafieldsToAdd[$key]['key'], $m['key']);
            $this->assertEquals($metafieldsToAdd[$key]['value'], $m['value']);
        }
        $this->assertEquals(count($metafieldsToAdd), $numMetafields);
        self::$api->delete('products/' . $resp['id']);
    }
}