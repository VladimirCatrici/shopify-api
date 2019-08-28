<?php
namespace ShopifyAPI\Tests;

use BadMethodCallException;
use VladimirCatrici\Shopify\API;
use VladimirCatrici\Shopify\Collection;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use LogicException;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase {

    /**
     * @var MockHandler
     */
    private static $mock;

    /**
     * @var API
     */
    private static $api;

    private /** @noinspection PhpUnusedPrivateFieldInspection */
        $countEndpointAvailable = [
        'customers',
        'customer_saved_searches',
        'products',
        'products\/\d+\/images',
        'products\/\d+\/variants',
        'product_listings',
        'custom_collections',
        'smart_collections',
        'collects',
        'price_rules',
        'draft_orders',
        'orders',
        'orders\/\d+\/transactions',
        'orders\/\d+\/fulfillments',
        'gift_cards',
        'checkouts',
        'checkouts\/\[a-z0-9]+\/payments',
        'blogs',
        'blogs\/\d+\/articles',
        'comments',
        'pages',
        'countries',
        'countries\/\d+\/provinces',
        'script_tags',
        'metafields',
        'locations',
        'redirects',
        'webhooks',
        'marketing_events',
        'events'
    ];

    public static function setUpBeforeClass() {
        self::$api = new API('test', 'test');
        self::$mock = new MockHandler();
        $handler = HandlerStack::create(self::$mock);
        self::$api = new API('test-domain', 'test-token', [
            'handler' => $handler,
            'max_attempts_on_server_errors' => 1,
            'max_attempts_on_rate_limit_errors' => 1
        ]);
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testCount() {
        self::$mock->append(new Response(200, ['X-Shopify-API-Version' => '2019-04'], '{"count": 1001}'));
        $collection = new Collection(self::$api, 'products');
        $this->assertEquals(1001, count($collection));
        $this->assertEquals(1001, $collection->count());
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testIteration() {
        $this->mockCollection(2, 10);
        self::$mock->append(new Response(200, [], '{}'));
        $products = new Collection(self::$api, 'products', ['limit' => 10]);
        $this->assertEquals(20, count($products));
        foreach ($products as $i => $product) {
            $this->assertNotNull($product);
            $this->assertEquals($i + 1000, $product['id']);
            $this->assertEquals('Test product ' . ++$i, $product['title']);
        }
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testIterationWithException() {
        // Preparing data
        self::$mock->append(new Response(200, [], '{"count": 3}'));
        $id = 1000;
        $pageProducts = [];
        for ($item = 0; $item < 2; $item++) {
            $pageProducts[] = [
                'id' => $id,
                'title' => 'Test product ' . ($id - 999)
            ];
            $id++;
        }
        $pageProductsJson = json_encode(['products' => $pageProducts]);
        self::$mock->append(new Response(200, [], $pageProductsJson));
        self::$mock->append(new Response(500));

        // Test
        $products = new Collection(self::$api, 'products', ['limit' => 2]);
        $this->assertEquals(3, count($products));
        foreach ($products as $i => $product) {
            $this->assertNotNull($product);
            $this->assertEquals($i + 1000, $product['id']);
            $this->assertEquals('Test product ' . ($i + 1), $product['title']);
            if ($i == 1) {
                $this->expectException(API\RequestException::class);
            }
        }
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testArrayAccessInterfaceOffsetGet() {
        $this->mockCollection(2, 10);
        $products = new Collection(self::$api, 'products', ['limit' => 10]);
        $this->assertEquals(1000, $products[0]['id']);
        $this->assertEquals(1005, $products[5]['id']);
        $this->assertEquals(1009, $products[9]['id']);
        $this->assertEquals(1010, $products[10]['id']);
        $this->assertEquals(1019, $products[19]['id']);
        $this->assertEquals(1019, $products['19']['id']);
        $this->assertNull($products[20]['id']);
        $this->assertNull($products['test']);
        $this->assertNull($products['19.1']);
        $this->assertNull($products[-1]);
        $this->mockCollection(2, 10, false);
        $this->assertEquals(1001, $products[1]['id']);
        $this->assertEquals(1011, $products[11]['id']);

        return $products;
    }

    /**
     * @depends testArrayAccessInterfaceOffsetGet
     * @param $products
     */
    public function testArrayAccessOffsetPush($products) {
        $this->expectException(LogicException::class);
        $products[] = 1;
    }

    /**
     * @depends testArrayAccessInterfaceOffsetGet
     * @param $products
     */
    public function testArrayAccessOffsetSet($products) {
        $this->expectException(LogicException::class);
        $products[0] = 1;
    }

    /**
     * @depends testArrayAccessInterfaceOffsetGet
     * @param $products
     */
    public function testArrayAccessOffsetSet2($products) {
        $this->expectException(LogicException::class);
        $products[] = 1;
    }

    /**
     * @depends testArrayAccessInterfaceOffsetGet
     * @param Collection $products
     */
    public function testArrayAccessUnset($products) {
        $this->expectException(LogicException::class);
        unset($products[0]);
    }

    /**
     * @depends testArrayAccessInterfaceOffsetGet
     * @param Collection $products
     */
    public function testArrayAccessOffsetExists($products) {
        $this->assertTrue(isset($products[0]));
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testArrayAccessOffsetExistsThrowsExceptionForCollectionsBasedOnCursorPagination() {
        $mock = new MockHandler();
        $mock->append(new Response(200, [], '{"count": 2}'));
        $mock->append(new Response(200, [], '{"products": [{"id": 1, "title": "Product 1"}, {"id": 2, "title": "Product 2"}]}'));
        $handler = HandlerStack::create($mock);
        $api = new API('test', 'test', [
            'api_version' => '2019-07',
            'handler' => $handler
        ]);
        $products = new Collection($api, 'products');
        $this->expectException(LogicException::class);
        /** @noinspection PhpExpressionResultUnusedInspection */
        isset($products[0]);
    }

    /**
     * @dataProvider cursorBasedPaginationEndpointsThatSupportCountOperation
     * @param $apiVersion
     * @param $endpoint
     * @return void [$apiVersion, $endpoint] to be used by testCursorBasedPaginationThrowsExceptionOnArrayAccess
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testArrayAccessReadOperationsWorkInEligibleApiVersions($apiVersion, $endpoint) {
        $apiVersion = $apiVersion == '2019-10' ? '2019-07' : '2019-04';
        $mock = new MockHandler();
        $mock->append(new Response(200, [], '{"count": 2}'));
        $mock->append(new Response(200, [], '{"products": [{"id": 1, "title": "Product 1"}, {"id": 2, "title": "Product 2"}]}'));
        $handler = HandlerStack::create($mock);
        $api = new API('test', 'test', [
            'api_version' => $apiVersion,
            'handler' => $handler,
            'max_attempts_on_server_errors' => 1,
            'max_attempts_on_rate_limit_errors' => 1
        ]);
        $products = new Collection($api, $endpoint, ['limit' => 2]);
        $this->assertEquals('Product 1', $products[0]['title']);
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testCursorBasedPagination() {
        self::$api->setVersion('2019-07');
        $this->mockCollection(3, 20, true, true);
        $products = new Collection(self::$api, 'products', ['limit' => 20]);
        $this->assertEquals(60, count($products));
        $numProducts = 0;
        foreach ($products as $key => $product) {
            $numProducts++;
            $this->assertEquals(1000 + $key, $product['id']);
        }
        $this->assertEquals(60, $numProducts);
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testCursorBasedPaginationWithoutPages() {
        self::$api->setVersion('2019-07');
        $this->mockCollection(1, 5, true, true);
        $products = new Collection(self::$api, 'products', ['limit' => 5]);
        $this->assertEquals(5, count($products));
        $numProducts = 0;
        foreach ($products as $key => $product) {
            $numProducts++;
            $this->assertEquals(1000 + $key, $product['id']);
        }
        $this->assertEquals(5, $numProducts);
    }/** @noinspection PhpUnusedParameterInspection */
    /** @noinspection PhpUnusedParameterInspection */

    /**
     * @dataProvider cursorBasedPaginationEndpointsThatSupportCountOperation
     * @param $apiVersion
     * @param $endpoint
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testCursorBasedPaginationThrowsExceptionOnArrayAccess($apiVersion, $endpoint) {
        $mock = new MockHandler();
        $mock->append(new Response(200, [], '{"count": 100}'));
        $handler = HandlerStack::create($mock);
        $api = new API('test', 'test', [
            'api_version' => $apiVersion,
            'handler' => $handler,
            'max_attempts_on_server_errors' => 1,
            'max_attempts_on_rate_limit_errors' => 1
        ]);
        $products = new Collection($api, $endpoint, ['limit' => 2]);
        $this->expectException(LogicException::class);
        $products[0];
    }

    // TODO: REWORK
    public function cursorBasedPaginationEndpointsThatSupportCountOperation() {
        $output = [];
        $data = [
            '2019-07' => [
                // TODO: Add more
                'products', 'metafields', 'collects'
            ],
            '2019-10' => [
                // TODO: Add more
                'blogs', 'comments', 'customers', 'orders'
            ]
        ];
        foreach ($data as $apiVersion => $endpoints) {
            foreach ($endpoints as $endpoint) {
                $output[] = [$apiVersion, $endpoint];
            }
        }
        return $output;
    }

    public function cursorBasedPaginationEndpointsAll() {
        $output = [];
        $data = [
            '2019-07' => [
                'article_saved_searches', 'balance_transaction_saved_searches', 'blog_saved_searches',
                'checkout_saved_searches', 'collects', 'collection_listings',
                'collection_listings/1234567890/product_ids', 'collection_saved_searches',
                'comment_saved_searches', 'customer_saved_searches', 'discount_code_saved_searches',
                'draft_order_saved_searches', 'events', 'file_saved_searches', 'gift_card_saved_searches',
                'inventory_transfer_saved_searches', 'metafields', 'page_saved_searches', 'products', 'search',
                'product_listings', 'product_saved_searches', 'variants\/search', 'product_variant_saved_searches',
                'redirect_saved_searches', 'transfer_saved_searches'
            ],
            '2019-10' => [
                'blogs/1234567890/articles', 'blogs', 'comments', 'custom_collections', 'customers/1234567890/addresses',
                'customers', 'customers/search', 'price_rules/1234567890/discount_codes', 'shopify_payments/disputes',
                'draft_orders', 'orders/1234567890/fulfillments', 'gift_cards', 'gift_cards/search', 'inventory_items',
                'inventory_levels', 'locations/1234567890/inventory_levels', 'marketing_events', 'orders',
                'orders/1234567890/risks', 'pages', 'shopify_payments/payouts', 'price_rules/product_ids',
                'product_listings/product_ids', 'variants', 'redirects', 'orders/1234567890/refunds', 'reports', 'script_tags',
                'smart_collections', 'tender_transactions', 'shopify_payments/balance/transactions', 'webhooks'
            ]
        ];
        foreach ($data as $apiVersion => $endpoints) {
            foreach ($endpoints as $endpoint) {
                $output[] = [$apiVersion, $endpoint];
            }
        }
        return $output;
    }


    /**
     * @dataProvider  endpointsThatDontSupportCountOperation
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testEndpointWithoutCountOperation() {
        $api = new API('test', 'test', [
            'api_version' => '2019-07'
        ]);
        $inventoryLevels = new Collection($api, 'inventory_levels');
        $this->expectException(BadMethodCallException::class);
        count($inventoryLevels);
    }

    public function endpointsThatDontSupportCountOperation() {
        return [
            ['inventory_levels'],
            ['inventory_items'],
            ['locations/1234567890/inventory_levels'],
            ['countries'],
            ['customers/1234567890/addresses']
        ];
    }

    /**
     * @dataProvider dataForTestingEndpointsPaginationThatDontSupportCountOperation
     * @param $apiVersion
     * @param $endpoint
     * @param $numPages
     * @param $itemsPerPage
     * @param $lastPageItemsCount
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function testCursorBasedPaginationForEndpointsThatDontSupportCountOperation(
        $apiVersion, $endpoint, $numPages, $itemsPerPage, $lastPageItemsCount
    ) {
        if ($numPages > 0) {
            $data = [];
            for ($i = 0; $i < $numPages; $i++) {
                $pageData = [];
                for ($j = 0; $j < $itemsPerPage; $j++) {
                    $pageData[] = [
                        'inventory_item_id' => 1,
                        'location_id' => 2,
                        'available' => 3
                    ];
                    if ($i == $numPages - 1 && $j == $lastPageItemsCount - 1) {
                        break;
                    }
                }
                $data[] = $pageData;
            }
            if ($itemsPerPage == $lastPageItemsCount) { // Next empty page
                $data[] = [];
            }
        }
        $data[] = [];

        $mock = new MockHandler();
        $handler = HandlerStack::create($mock);
        $api = new API('test', 'test', [
            'api_version' => $apiVersion,
            'handler' => $handler
        ]);

        foreach ($data as $page => $items) {
            $headers = [];
            $links = [];
            if ($page > 0) {
                $links[] = '<https://test.myshopify.com/admin/api/' . $endpoint . '/' . $endpoint . '.json?limit=' . $itemsPerPage . '&page_info=' . $this->rndStr() . '>; rel="previous"';
            }
            if ($page < ($numPages - 1)) {
                $links[] = '<https:$$//test.myshopify.com/admin/api/' . $endpoint . '/' . $endpoint . '.json?limit=' . $itemsPerPage . '&page_info=' . $this->rndStr() . '>; rel="next"';
            }
            if (count($links) > 0) {
                $headers['Link'] = implode(',', $links);
            }
            $itemsJson = json_encode([$endpoint => $items]);
            $mock->append(new Response(200, $headers, $itemsJson));
        }

        $items = new Collection($api, 'inventory_levels', ['limit' => $itemsPerPage]);
        $itemCount = 0;
        foreach ($items as $index => $item) {
            $this->assertEquals($itemCount, $index);
            $itemCount++;
        }
        if ($numPages > 0) {
            $this->assertEquals($numPages * $itemsPerPage - $itemsPerPage + $lastPageItemsCount, $itemCount);
        } else {
            $this->assertEquals(0, $itemCount);
        }
    }

    public function dataForTestingEndpointsPaginationThatDontSupportCountOperation() {
        $output = [];
        $apiVersions = ['2019-04', '2019-07'];
        foreach ($apiVersions as $apiVersion) {
            // ['api_version', 'endpoint', 'num_pages', 'items_per_page', 'last_page_items']
            $output[] = [$apiVersion, 'inventory_levels', 3, 2, 2];
            $output[] = [$apiVersion, 'inventory_levels', 3, 2, 1];
            $output[] = [$apiVersion, 'inventory_levels', 1, 2, 2];
            $output[] = [$apiVersion, 'inventory_levels', 1, 2, 1];
            $output[] = [$apiVersion, 'inventory_levels', 0, 2, 0];
        }
        return $output;
    }

    /**
     * Generates and adds to the Mock object list of responses for the Collection object.
     * @param $numPages
     * @param $limit
     * @param bool $includeCountResponse Whether the count response should be added as the first response. BY default
     * it's set to TRUE, might be useful when you need to set it to FALSE, e.g. testArrayAccessInterface();
     * @param bool $cursorBasedPagination
     */
    private function mockCollection($numPages, $limit, $includeCountResponse = true, $cursorBasedPagination = false) {
        if ($includeCountResponse) {
            self::$mock->append(new Response(200, [], '{"count": ' . $numPages * $limit . '}'));
        }
        $id = 1000;
        for ($page = 0; $page < $numPages; $page++) {
            $pageProducts = [];
            for ($item = 0; $item < $limit; $item++) {
                $pageProducts[] = [
                    'id' => $id,
                    'title' => 'Test product ' . ($id - 999)
                ];
                $id++;
            }
            $pageProductsJson = json_encode(['products' => $pageProducts]);
            $headers = [];
            if ($cursorBasedPagination) {
                $links = [];
                if ($page > 0) {
                    $links[] = '<https://test.myshopify.com/admin/api/2019-07/products.json?limit=20&page_info=' . $this->rndStr() . '>; rel="previous"';
                }
                if ($page < ($numPages - 1)) {
                    $links[] = '<https://test.myshopify.com/admin/api/2019-07/products.json?limit=20&page_info=' . $this->rndStr() . '>; rel="next"';
                }
                if (count($links) > 0) {
                    $headers['Link'] = implode(',', $links);
                }
            }
            self::$mock->append(new Response(200, $headers, $pageProductsJson));
        }
    }

    private function rndStr($length = 12) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}