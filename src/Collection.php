<?php

declare(strict_types=1);

namespace VladimirCatrici\Shopify;

use BadMethodCallException;
use Countable;
use Iterator;

class Collection implements Iterator, Countable
{
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
        ]
    ];

    /**
     * Collection constructor.
     * @param ClientInterface $shopify
     * @param $endpoint
     * @param array $options
     * @throws Exception\RequestException
     */
    public function __construct(ClientInterface $shopify, $endpoint, $options = [])
    {
        $this->api = $shopify;
        $this->endpoint = $endpoint;
        $this->paginationType = (new PaginationType($endpoint))->getType();

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
    public function current()
    {
        return $this->items[$this->partIndex];
    }

    public function key()
    {
        return $this->currentIndex;
    }

    public function next()
    {
        $this->partIndex++;
        if ($this->partIndex == $this->chunkCount) {
            $this->page++;
            if ($this->paginationType === PaginationType::SINCE ||
                ($this->paginationType === PaginationType::CURSOR && !empty($this->nextPageInfo))
            ) {
                $this->fetch();
            }
        }
        $this->currentIndex++;
    }

    public function rewind()
    {
        $this->page = 1;
        $this->fetch();
        $this->currentIndex = 0;
    }

    public function valid()
    {
        return isset($this->items[$this->partIndex]);
    }

    /**
     * @return int
     */
    public function count()
    {
        if ($this->count === null) {
            throw new BadMethodCallException('This endpoint does not support "count" operation');
        }
        return $this->count;
    }

    /**
     * Fetches Shopify items based on current parameters like page, limit and options specified on object creation
     */
    private function fetch()
    {
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
                    break;
                }
                $options['page_info'] = $this->nextPageInfo;
                break;
            case PaginationType::SINCE:
                $options['since_id'] = $this->page == 1 ? 1 : $this->items[$this->partIndex - 1]['id'];
                $options += $this->options;
                break;
        }
        $this->items = $this->api->get($this->endpoint, $options);
        $this->chunkCount = count($this->items);
        $this->partIndex = 0;
        if ($this->paginationType == PaginationType::CURSOR) {
            $this->updateNextPageInfo();
        }
    }

    private function updateNextPageInfo()
    {
        if (!array_key_exists('Link', $this->api->respHeaders) && !array_key_exists('link', $this->api->respHeaders)) { // All results on the same page
            $this->nextPageInfo = null;
            return;
        }
        $linkHeaderValue = array_key_exists('Link', $this->api->respHeaders) ? $this->api->respHeaders['Link'][0] : $this->api->respHeaders['link'][0];
        preg_match('/page_info=([^>]+)>;\srel="next"/i', $linkHeaderValue, $matches);
        if (empty($matches[1])) { // No next page
            $this->nextPageInfo = null;
            return;
        }
        $this->nextPageInfo = $matches[1];
    }
}
