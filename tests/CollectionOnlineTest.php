<?php


namespace ShopifyAPI\Test;

use VladimirCatrici\Shopify\API;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\TestCase;
use VladimirCatrici\Shopify\Collection;

class CollectionOnlineTest extends TestCase {
    /**
     * @var API
     */
    public static $api;

    public static function setUpBeforeClass() {
        self::$api = new API(getenv('SHOPIFY_API_DOMAIN'), getenv('SHOPIFY_API_PASSWORD'), [
            'max_attempts_on_server_errors' => 3,
            'max_attempts_on_rate_limit_errors' => 10
        ]);
    }

    public function testIteration() {
        $count = self::$api->get('products/count');

        if ($count < 25) { // Create some demo products for testing purposes
            $listOfCreatedProducts = [];
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
                $listOfCreatedProducts[] = $product['id'];
            }
        }

        $products = new Collection(self::$api, 'products');
        $this->assertEquals($count + 5, count($products));
        $iterationCount = 0;
        foreach ($products as $p) {
            $iterationCount++;
        }
        $this->assertEquals($count + 5, $iterationCount);

        if (!empty($listOfCreatedProducts)) {
            foreach ($listOfCreatedProducts as $id) {
                self::$api->delete('products/' . $id);
                $this->assertEquals(200, self::$api->respCode);
            }
        }
    }
}