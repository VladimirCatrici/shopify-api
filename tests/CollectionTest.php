<?php
namespace ShopifyAPI\Tests;

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
        self::$mock->append(new Response(200, [], '{"count": 1001}'));
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
     * Generates and adds to the Mock object list of responses for the Collection object.
     * @param $numPages
     * @param $limit
     * @param bool $includeCountResponse Whether the count response should be added as the first response. BY default
     * it's set to TRUE, might be useful when you need to set it to FALSE, e.g. testArrayAccessInterface();
     */
    private function mockCollection($numPages, $limit, $includeCountResponse = true) {
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
            self::$mock->append(new Response(200, [], $pageProductsJson));
        }
    }
}