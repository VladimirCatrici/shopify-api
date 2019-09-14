<?php
namespace VladimirCatrici\Shopify;

use BadMethodCallException;
use Countable;
use Exception;
use Iterator;

class Collection implements Iterator, Countable {

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

    private $chunkCount;

    private $fetched = false;

    private $partIndex = 0;

    private $count;

    private $currentIndex = 0;

    /**
     * @var PaginationType
     */
    private $paginationType;

    private $isCursorBasedPagination = false;

    private $countEndpointAvailable = [
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
        '.+\/\d+\/metafields',
        'locations',
        'redirects',
        'webhooks',
        'marketing_events',
        'events'
    ];

    private $sinceIdSupport = [
        'shopify_payments\/disputes',
        'shopify_payments\/payouts',
        'shopify_payments\/balance\/transactions',
        'reports',
        'application_charges',
        'recurring_application_charges',
        'customers',
        'customers\/\d+\/addresses',
        'customer_saved_searches',
        'price_rules',
        'events',
        'webhooks',
        'metafields ',
        'articles',
        'blogs',
        'blogs\/\d+\/articles',
        'comments',
        'pages',
        'redirects',
        'script_tags',
        'checkouts',
        'draft_orders',
        'orders',
        'orders\/\d+\/transactions',
        'gift_cards',
        'collects',
        'custom_collections',
        'products',
        'products\/\d+\/images',
        'products\/\d+\/variants',
        'smart_collections',
        'metafields'
    ];

    private $pageBasedPaginationEndpoints = [
        'inventory_items',
        'inventory_levels',
        'marketing_events',
    ];

    private $cursorBasedPaginationEndpoints = [
        /**
         * According to: https://help.shopify.com/en/api/versioning/release-notes/2019-07
         */
        '2019-07' => [
            'collects',
            'collection_listings',
            'collection_listings\/%d+\/product_ids',
            'customer_saved_searches',
            'events',
            'metafields',
            'products',
            'search',
            'product_listings',
            'variants\/search',
        ],

        /**
         * According to: https://help.shopify.com/en/api/versioning/release-notes/2019-10
         */
        '2019-10' => [
            'abandoned_checkouts',
            'checkouts',
            'blogs\/\d+\/articles',
            'blogs',
            'comments',
            'customers',
            'customers\/search',
            'customers\/\d+\/addresses',
            'custom_collections',
            'smart_collections',
            'price_rules',
            'price_rules\/product_ids',
            'price_rules\/\d+\/discount_codes',
            'shopify_payments\/disputes',
            'gift_cards',
            'gift_cards\/search',
            'inventory_items',
            'inventory_levels',
            'locations\/\d+\/inventory_levels',
            'marketing_events',
            'draft_orders',
            'orders',
            'orders\/\d+\/fulfillments',
            'orders\/\d+\/risks',
            'shopify_payments\/payouts',
            'shopify_payments\/balance\/transactions',
            'pages',
            'product_listings\/product_ids',
            'products\/\d+\/variants',
            'variants',
            'redirects',
            'orders\/\d\/refunds',
            'reports',
            'script_tags',
            'tender_transactions',
            'webhooks'
        ]
    ];

    /**
     * Collection constructor.
     * @param API $shopify
     * @param $endpoint
     * @param array $options
     * @throws API\RequestException
     * @throws Exception
     */
    public function __construct(API $shopify, $endpoint, $options = []) {
        $this->api = $shopify;
        $this->endpoint = $endpoint;

        $this->detectPaginationType();
        if (empty($this->paginationType)) {
            throw new Exception(sprintf('The `%s` endpoint is not supported and cannot be used as a collection object, 
                pagination type is not specified',
                $endpoint));
        }

        if (array_key_exists('limit', $options)) {
            $this->limit = $options['limit'];
            unset($options['limit']);
        }
        $this->options = $options;

        $countEndpointAvailable = in_array($endpoint, $this->countEndpointAvailable);
        if (!$countEndpointAvailable) {
            foreach ($this->countEndpointAvailable as $countEndpoint) {
                if (preg_match('/\\\/', $countEndpoint)) { // RegExp
                    if (preg_match('/' . $countEndpoint . '/i', $endpoint)) {
                        $countEndpointAvailable = true;
                        break;
                    }
                }
            }
        }
        if ($countEndpointAvailable) {
            $this->count = $this->api->get($this->endpoint . '/count', $this->options);
            $this->numPages = ceil($this->count / $this->limit);
        }
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
     */
    public function next() {
        $this->partIndex++;
        if ($this->partIndex == $this->chunkCount) {
            $this->page++;
            switch ($this->paginationType) {
                case PaginationType::CURSOR:
                    if (!empty($this->nextPageInfo)) {
                        $this->fetch();
                    }
                    break;
                case PaginationType::SINCE:
                    $this->fetch();
                    break;
                default: // PaginationType::PAGE
                    $this->fetch();
                    if (count($this->items) == 0) {
                        $this->numPages = $this->page;
                    }
            }
        }
        $this->currentIndex++;
    }

    /**
     * @throws API\RequestException
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
        if (is_null($this->count)) {
            throw new BadMethodCallException('This endpoint does not support "count" operation');
        }
       return $this->count;
    }

    /**
     * Fetches Shopify items based on current parameters like page, limit and options specified on object creation
     * @throws API\RequestException
     */
    private function fetch() {
        $options = [
            'limit' => $this->limit
        ];
        switch ($this->paginationType) {
            case PaginationType::CURSOR:
                /**
                 * Cursor-based pagination accepts other parameters rather than limit only in the first request.
                 * To get next pages need you need only pass `limit` and `page_info` parameters.
                 */
                if ($this->page == 1) {
                    $options += $this->options;
                } else {
                    $options['page_info'] = $this->nextPageInfo;
                }
                break;
            case PaginationType::SINCE:
                if ($this->page == 1) {
                    $options['since_id'] = 1;
                } else {
                    $options['since_id'] = $this->items[$this->partIndex - 1]['id'];
                }
                $options += $this->options;
                break;
            default: // PaginationType::PAGE
                $options['page'] = $this->page;
                $options += $this->options;
        }
        $this->items = $this->api->get($this->endpoint, $options);
        $this->chunkCount = count($this->items);
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

    /**
     * @throws Exception
     * TODO: set cursor based pagination after the first request to get items. If it returns Link header, than it's cursor based pagination
     */
    private function detectPaginationType() {
        $apiVersion = $this->api->getVersion();

        if ($apiVersion >= '2019-07') {
            foreach ($this->cursorBasedPaginationEndpoints as $version => $endpointsRegEx) {
                if ($version <= $apiVersion) {
                    foreach ($endpointsRegEx as $re) {
                        if (preg_match('/' . $re . '/', $this->endpoint)) {
                            $this->isCursorBasedPagination = true;
                            $this->paginationType = PaginationType::CURSOR;
                            return;
                        }
                    }
                }
            }
        }

        foreach ($this->sinceIdSupport as $endpointRegEx) {
            if (preg_match('/' . $endpointRegEx . '/', $this->endpoint)) {
                $this->paginationType = PaginationType::SINCE;
                return;
            }
        }

        foreach ($this->pageBasedPaginationEndpoints as $endpointRegEx) {
            if (preg_match('/' . $endpointRegEx . '/', $this->endpoint)) {
                $this->paginationType = PaginationType::PAGE;
                return;
            }
        }
    }
}
