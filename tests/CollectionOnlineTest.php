<?php


namespace ShopifyAPI\Tests;

use VladimirCatrici\Shopify\API;
use PHPUnit\Framework\TestCase;
use VladimirCatrici\Shopify\Collection;
use VladimirCatrici\Shopify\Exception\RequestException;

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

    /**
     * @throws RequestException
     */
    public static function tearDownAfterClass() {
        if (!empty(self::$listOfCreatedProducts)) {
            foreach (self::$listOfCreatedProducts as $id) {
                self::$api->delete('products/' . $id);
                self::assertEquals(200, self::$api->respCode);
            }
        }
    }

    /**
     * @throws RequestException
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
     * @throws RequestException
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

    /**
     * This test create a product with multiple variant options. This would allow us to test page-based pagination,
     * making requests to `inventory_levels` endpoint for API versions before 2019-10.
     * // TODO: Remove this and all depended test after 2020-07
     * @return array Shopify product
     * @throws RequestException
     */
    public function testCreateProduct() {
        /*
         * Generate variants for a product. Product may have up to 3 options and maximum 100 variants. So need to make
         * sure to be in that limit.
         * To increase productivity I've commented some other option to produce a product with 10 variants only. This is
         * enough to execute pagination tests that are depends on result of this one.
         */
        $variants = [];
        $colors = ['Red', 'Green'/*, 'Blue', 'Black', 'Navy'*/];
        $sizes = ['XS', 'S', 'M', 'L', 'XL'];
        $materials = ['Cotton'/*, 'Polyester', 'Bamboo', 'Microfibre'*/];
        $numVariants = 0;
        foreach ($colors as $color) {
            foreach ($sizes as $size) {
                foreach ($materials as $material) {
                    $variants[] = [
                        'option1' => $color,
                        'option2' => $size,
                        'option3' => $material,
                        'inventory_management' => 'shopify'
                    ];
                    $numVariants++;
                }
            }
        }
        // Create product
        $resp = self::$api->post('products', [
            'product' => [
                'title' => 'Test product with many variants',
                'variants' => $variants,
                'options' => [
                    [
                        'name' => 'Color',
                        'values' => $colors
                    ],
                    [
                        'name' => 'Size',
                        'values' => $sizes
                    ],
                    [
                        'name' => 'Material',
                        'values' => $materials
                    ]
                ]
            ]
        ]);
        $this->assertCount($numVariants, $resp['variants']);
        return $resp;
    }

    /**
     * Test a common case when number of items in the collection is less than default limit per page (250)
     * @depends testCreateProduct
     * @param array $product
     * @return array Shopify product that was passed to input. It's required to test other pagination cases
     * @throws RequestException
     */
    public function testPageBasedPaginationLimitMaximum($product) {
        $this->doTestPageBasedPagination($product, 5, 250);
        return $product;
    }

    /**
     * Test a case when number of items in the collection equals to the limit per page
     * @depends testPageBasedPaginationLimitMaximum
     * @param array $product
     * @return array Shopify product that was passed to input. It's required to test other pagination cases
     * @throws RequestException
     */
    public function testPageBasedPaginationLimitSame($product) {
        $this->doTestPageBasedPagination($product, 5, 5);
        return $product;
    }

    /**
     * Test a case when number of items in the collection is twice more than limit per page, i.e. 3 requests to endpoint
     * @depends testPageBasedPaginationLimitSame
     * @param array $product
     * @return array Shopify product that was passed to input. It's required to test other pagination cases
     * @throws RequestException
     */
    public function testPageBasedPaginationLimitHalf($product) {
        $this->doTestPageBasedPagination($product, 10, 5);
        return $product;
    }

    /**
     * Test a case when number of items in the collection equals to 1 page + 1 item
     * @depends testPageBasedPaginationLimitHalf
     * @param array $product
     * @return array Shopify product that was passed to input. It's required to test other pagination cases
     * @throws RequestException
     */
    public function testPageBasedPaginationLimit3($product) {
        $this->doTestPageBasedPagination($product, 4, 3);
        return $product;
    }

    /**
     * Test a case when limit per page is equal to 1
     * @depends testPageBasedPaginationLimit3
     * @param array $product
     * @return array Shopify product that was passed to input. It's required to test other pagination cases
     * @throws RequestException
     */
    public function testPageBasedPaginationLimit1($product) {
        $this->doTestPageBasedPagination($product, 3, 1);
        return $product;
    }

    /**
     * @depends testPageBasedPaginationLimit1
     * @param array $product Shopify product created by testCreateProduct
     * @throws RequestException
     */
    public function testDeleteProduct($product) {
        self::$api->delete('products/' . $product['id']);
        $this->assertEquals(200, self::$api->respCode);
    }

    /**
     * This method is called from other tests above where name starts with testPageBasedPaginationLimitXXX.
     * @param array $product Shopify product
     * @param int $limit Maximum number of inventory items to use. Will be passed to endpoint as a filter parameter
     * @param int $itemPerPage Limit that will be passed as Collection option. It specifies the number of items per page
     * @return array Shopify product that was passed to input
     * @throws RequestException
     */
    private function doTestPageBasedPagination($product, $limit, $itemPerPage) {
        $inventoryItemIds = array_column($product['variants'], 'inventory_item_id');
        $inventoryItemIds = array_slice($inventoryItemIds, 0, $limit);
        // We should pass either a list of inventory item IDs or location IDs to inventory_levels endpoint.
        $inventoryLevelsCollection = new Collection(self::$api, 'inventory_levels', [
            'inventory_item_ids' => implode(',', $inventoryItemIds),
            'limit' => $itemPerPage
        ]);
        $numItemsInCollection = 0;
        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach ($inventoryLevelsCollection as $il) {
            $numItemsInCollection++;
        }
        $this->assertEquals($limit, $numItemsInCollection);
        return $product;
    }
}