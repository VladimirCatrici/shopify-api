<?php
namespace VladimirCatrici\Shopify;

use BadMethodCallException;
use Countable;
use Exception;
use Iterator;
use VladimirCatrici\Shopify\Exception\RequestException;

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

    private $items = [];

    private $chunkCount;

    private $partIndex = 0;

    private $count;

    private $currentIndex = 0;

    /**
     * @var PaginationType
     */
    private $paginationType;

    private $endpointsSupport = [
        // `count` endpoint available
        'count' => [
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
        ],
        // `since_id` pagination available
        'since' => [
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
        ],
        // Page-based pagination available (up to 2019-07 incl.)
        'page' => [
            'inventory_items',
            'inventory_levels',
            'marketing_events'
        ],
        'cursor' => [
            // According to: https://help.shopify.com/en/api/versioning/release-notes/2019-07
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
            // According to: https://help.shopify.com/en/api/versioning/release-notes/2019-10
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
        ],
        'no_pagination' => [
            'locations'
        ]
    ];

    /**
     * Collection constructor.
     * @param API $shopify
     * @param $endpoint
     * @param array $options
     * @throws RequestException
     * @throws Exception
     */
    public function __construct(API $shopify, $endpoint, $options = []) {
        $this->api = $shopify;
        $this->endpoint = $endpoint;
        $this->paginationType = $this->detectPaginationType();

        if (array_key_exists('limit', $options)) {
            $this->limit = $options['limit'];
            unset($options['limit']);
        }
        $this->options = $options;

        $countEndpointAvailable = in_array($endpoint, $this->endpointsSupport['count']);
        if (!$countEndpointAvailable) {
            foreach ($this->endpointsSupport['count'] as $countEndpoint) {
                if (preg_match('/\\\\/', $countEndpoint)) { // RegExp
                    if (preg_match('/' . $countEndpoint . '/i', $endpoint)) {
                        $countEndpointAvailable = true;
                        break;
                    }
                }
            }
        }
        if ($countEndpointAvailable) {
            $this->count = $this->api->get($this->endpoint . '/count', $this->options);
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
     * @throws RequestException
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
                case PaginationType::NOT_REQUIRED:
                    break; // Do nothing, no action required
                default: // PaginationType::PAGE
                    $this->fetch();
            }
        }
        $this->currentIndex++;
    }

    /**
     * @throws RequestException
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
        if ($this->count === null) {
            throw new BadMethodCallException('This endpoint does not support "count" operation');
        }
       return $this->count;
    }

    /**
     * Fetches Shopify items based on current parameters like page, limit and options specified on object creation
     * @throws RequestException
     */
    private function fetch() {
        $options = [
            'limit' => $this->limit
        ];
        switch ($this->paginationType) {
            case PaginationType::CURSOR:
                /*
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
                $options['since_id'] = $this->page == 1 ? 1 : $this->items[$this->partIndex - 1]['id'];
                $options += $this->options;
                break;
            case PaginationType::NOT_REQUIRED:
                break; // Do nothing, no need to add options
            default: // PaginationType::PAGE
                $options['page'] = $this->page;
                $options += $this->options;
        }
        $this->items = $this->api->get($this->endpoint, $options);
        $this->chunkCount = count($this->items);
        $this->partIndex = 0;
        if ($this->paginationType == PaginationType::CURSOR) {
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

    /**
     * @throws Exception
     * TODO: set cursor based pagination after the first request to get items. If it returns Link header, than it's cursor based pagination
     */
    private function detectPaginationType() {
        if (in_array($this->endpoint, $this->endpointsSupport['no_pagination'])) {
            return PaginationType::NOT_REQUIRED;
        }

        if ($this->supportsCursorBasedPagination()) {
            return PaginationType::CURSOR;
        }

        if ($this->supportsSincePagination()) {
            return PaginationType::SINCE;
        }

        if ($this->supportsPageBasedPagination()) {
            return PaginationType::PAGE;
        }

        throw new Exception(sprintf('Pagination type is not defined for `%s` endpoint', $this->endpoint));
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function supportsCursorBasedPagination() {
        $apiVersion = $this->api->getVersion();
        if ($apiVersion >= '2019-07') {
            foreach ($this->endpointsSupport['cursor'] as $version => $endpointsRegEx) {
                if ($version > $apiVersion) {
                    continue;
                }
                foreach ($endpointsRegEx as $re) {
                    if (!preg_match('/' . $re . '/', $this->endpoint)) {
                        continue;
                    }
                    return true;
                }
            }
        }
        return false;
    }

    private function supportsSincePagination() {
        foreach ($this->endpointsSupport['since'] as $endpointRegEx) {
            if (preg_match('/' . $endpointRegEx . '/', $this->endpoint)) {
                return true;
            }
        }
        return false;
    }

    private function supportsPageBasedPagination() {
        foreach ($this->endpointsSupport['page'] as $endpointRegEx) {
            if (preg_match('/' . $endpointRegEx . '/', $this->endpoint)) {
                return true;
            }
        }
        return false;
    }
}
