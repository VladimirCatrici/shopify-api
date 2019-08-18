<?php
namespace VladimirCatrici\Shopify;

use ArrayAccess;
use Countable;
use GuzzleHttp\Exception\GuzzleException;
use Iterator;
use LogicException;

class Collection implements Iterator, Countable, ArrayAccess {

    private $endpoint;

    private $options;

    private $api;

    private $limit = 250;

    private $page = 1;
    /**
     * @var string A unique ID used to access a certain page of results. The page_info parameter can't be modified and
     * must be used exactly as it appears in the link header URL.
     * e.g. header:
     * Link: "<https://{shop}.myshopify.com/admin/api/2019-07/products.json?page_info=hijgklmn&limit=3>; rel=next"
     */
    private $nextPageInfo;

    private $numPages;

    private $items = [];

    private $fetched = false;

    private $partIndex = 0;

    private $count;

    private $currentIndex = 0;

    private $isCursorBasedPagination = false;

    private $cursorBasedPaginationEndpoints = [
        /**
         * According to: https://help.shopify.com/en/api/versioning/release-notes/2019-07
         */
        '2019-07' => [
            'article_saved_searches', 'balance_transaction_saved_searches', 'blog_saved_searches',
            'checkout_saved_searches', 'collects', 'collection_listings',
            'collection_listings\/%d+\/product_ids', 'collections', 'collection_saved_searches',
            'comment_saved_searches', 'customer_saved_searches', 'discount_code_saved_searches',
            'draft_order_saved_searches', 'events', 'file_saved_searches', 'gift_card_saved_searches',
            'inventory_transfer_saved_searches', 'metafields', 'page_saved_searches', 'products', 'search',
            'product_listings', 'product_saved_searches', 'variants\/search', 'product_variant_saved_searches',
            'redirect_saved_searches', 'transfer_saved_searches'
        ],

        /**
         * According to: https://help.shopify.com/en/api/versioning/release-notes/2019-10
         */
        '2019-10' => [
            'blogs\/\d+\/articles', 'blogs', 'comments', 'custom_collections', 'customers\/\d+\/addresses',
            'customers', 'customers\/search', 'price_rules\/\d+\/discount_codes', 'shopify_payments\/disputes',
            'draft_orders', 'orders\/\d+\/fulfillments', 'gift_cards', 'gift_cards\/search', 'inventory_items',
            'inventory_levels', 'locations\/\d+\/inventory_levels', 'marketing_events', 'orders',
            'orders\/\d+\/risks', 'pages', 'shopify_payments\/payouts', 'price_rules\/product_ids',
            'product_listings\/product_ids', 'variants', 'redirects', 'orders\/\d\/refunds', 'reports', 'script_tags',
            'smart_collections', 'tender_transactions', 'shopify_payments\/balance\/transactions', 'webhooks'
        ]
    ];

    /**
     * Collection constructor.
     * @param API $shopify
     * @param $endpoint
     * @param array $options
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function __construct(API $shopify, $endpoint, $options = []) {
        $this->api = $shopify;
        $this->endpoint = $endpoint;
        if (array_key_exists('limit', $options)) {
            $this->limit = $options['limit'];
            unset($options['limit']);
        }
        $this->options = $options;

        $this->count = $this->api->get($this->endpoint . '/count', $this->options);
        $this->numPages = ceil($this->count / $this->limit);

        $this->setPaginationType();
    }

    /**
     * @return array
     */
    public function current() {
        return $this->items[$this->partIndex];
    }

    public function key() {
        return $this->currentIndex;
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function next() {
        $this->partIndex++;
        if ($this->partIndex == $this->limit) {
            if ($this->isCursorBasedPagination) {
                if (!empty($this->nextPageInfo)) {
                    $this->fetch();
                }
            } elseif ($this->page < $this->numPages) {
                $this->page++;
                $this->fetch();
            }
        }
        $this->currentIndex++;
    }

    /**
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function rewind() {
        $this->page = 1;
        $this->fetch();
        $this->currentIndex = 0;
    }

    public function valid() {
        return isset($this->items[$this->partIndex]);
    }

    /**
     * @return int
     */
    public function count() {
       return $this->count;
    }

    /**
     * @deprecated Cannot be used if Shopify API version switched to cursor-based pagination for this endpoint.
     * Cursor-based pagination released in 2019-07 for some endpoints, and more endpoints to be added in 2019-10.
     * More details: https://help.shopify.com/en/api/guides/paginated-rest-results
     * @param mixed $offset
     * @return mixed|null
     * @throws API\RequestException
     * @throws GuzzleException
     */
    public function offsetGet($offset) {
        if ($this->isCursorBasedPagination) {
            throw new LogicException('ArrayAccess cannot be used for endpoints switched to cursor-based pagination');
        }
        trigger_error('ArrayAccess interface read operations are deprecated for \VladimirCatrici\Shopify\Collection objects', E_USER_DEPRECATED);
        if (!is_int($offset) || $offset < 0 || $offset >= $this->count) {
            return null;
        }
        $offsetPage = $this->offset2page($offset);
        if ($offsetPage != $this->page || !$this->fetched) {
            $this->page = $offsetPage;
            $this->fetch();
        }
        $this->partIndex = $this->offset2partIndex($offset);
        return isset($this->items[$this->partIndex]) ? $this->items[$this->partIndex] : null;
    }

    public function offsetExists($offset) {
        if ($this->isCursorBasedPagination) {
            throw new LogicException('ArrayAccess cannot be used for endpoints switched to cursor-based pagination');
        }
        trigger_error('ArrayAccess interface read operations are deprecated for \VladimirCatrici\Shopify\Collection objects', E_USER_DEPRECATED);
        return is_int($offset) && $offset >= 0 && $offset < $this->count;
    }

    public function offsetSet($offset, $value) {
        throw new LogicException('Shopify collection is read-only. You cannot add new items or change existing');
    }

    public function offsetUnset($offset) {
        throw new LogicException('Shopify collection is read-only. Items deletion prohibited');
    }

    /**
     * Fetches Shopify items based on current parameters like page, limit and options specified on object creation
     * @throws GuzzleException
     * @throws API\RequestException
     */
    private function fetch() {
        $options = [
            'limit' => $this->limit
        ];
        if ($this->isCursorBasedPagination) {
            /**
             * Cursor-based pagination accepts other parameters rather than limit only in the first request.
             * To get next pages need you need only pass `limit` and `page_info` parameters.
             */
            if ($this->page == 1) {
                $options += $this->options;
            } else {
                $options['page_info'] = $this->nextPageInfo;
            }
        } else {
            $options['page'] = $this->page;
            $options += $this->options;
        }
        $this->items = $this->api->get($this->endpoint, $options);
        $this->fetched = true;
        $this->partIndex = 0;
        if ($this->isCursorBasedPagination) {
            $this->updateNextPageInfo();
        }
    }

    private function updateNextPageInfo() {
        if (!array_key_exists('Link', $this->api->respHeaders)) { // All results on the same page
            $this->nextPageInfo = null;
            return;
        }
        $linkHeaderValue = $this->api->respHeaders['Link'][0];
        preg_match('/page_info=([^>]+)>;\srel="next"/i', $linkHeaderValue, $matches);
        if (empty($matches[1])) { // No next page
            $this->nextPageInfo = null;
            return;
        }

        $this->nextPageInfo = $matches[1];
    }

    private function offset2page($offset) {
        return floor($offset / $this->limit) + 1;
    }

    private function offset2partIndex($offset) {
        return $offset < $this->limit ? $offset : $offset % $this->limit;
    }

    private function setPaginationType() {
        $apiVersion = $this->api->getVersion();
        if ($apiVersion == '2019-04') {
            $this->isCursorBasedPagination = false;
            return;
        }

        foreach ($this->cursorBasedPaginationEndpoints as $version => $endpointsRegEx) {
            if ($version <= $apiVersion) {
                foreach ($endpointsRegEx as $re) {
                    if (preg_match('/' . $re . '/', $this->endpoint)) {
                        $this->isCursorBasedPagination = true;
                        break 2;
                    }
                }
            }
        }
    }
}
