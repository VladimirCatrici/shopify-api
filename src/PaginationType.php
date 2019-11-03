<?php

namespace VladimirCatrici\Shopify;

use Exception;

class PaginationType {
    public const NOT_REQUIRED = 0;
    public const CURSOR = 1;
    public const SINCE = 2;
    public const PAGE = 3;
    /**
     * @var int
     */
    private $type;

    private $endpointsSupport = [
        // `since_id` pagination available
        PaginationType::SINCE => [
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
        PaginationType::PAGE => [
            'inventory_items',
            'inventory_levels',
            'marketing_events'
        ],
        PaginationType::CURSOR => [
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
        PaginationType::NOT_REQUIRED => [
            'locations'
        ]
    ];

    /**
     * PaginationType constructor.
     * @param $endpoint
     * @param $apiVersion
     * @throws Exception
     */
    public function __construct($endpoint, $apiVersion = null) {
        if ($this->supportsPagination(PaginationType::NOT_REQUIRED, $endpoint)) {
            $this->type = PaginationType::NOT_REQUIRED;
            return;
        }

        if ($this->supportsPagination(PaginationType::CURSOR, $endpoint, $apiVersion)) {
            $this->type = PaginationType::CURSOR;
            return;
        }

        if ($this->supportsPagination(PaginationType::SINCE, $endpoint)) {
            $this->type = PaginationType::SINCE;
            return;
        }

        if ($this->supportsPagination(PaginationType::PAGE, $endpoint)) {
            $this->type = PaginationType::PAGE;
            return;
        }

        throw new Exception(sprintf('Pagination type is not defined for `%s` endpoint', $endpoint));
    }

    /**
     * @return int
     */
    public function getType(): int {
        return $this->type;
    }

    /**
     * @param int $type
     */
    public function setType(int $type): void {
        $this->type = $type;
    }

    /**
     * @param int $type Pagination type const e.g. PaginationType::CURSOR or PaginationType::SINCE
     * @return bool
     * @throws Exception
     */
    private function supportsPagination(int $type, $endpoint, $apiVersion = null) {
        if ($type == PaginationType::CURSOR) {
            if ($apiVersion >= '2019-07') {
                foreach ($this->endpointsSupport[PaginationType::CURSOR] as $version => $endpointsRegEx) {
                    if ($version > $apiVersion) {
                        continue;
                    }
                    foreach ($endpointsRegEx as $re) {
                        if (!preg_match('/' . $re . '/', $endpoint)) {
                            continue;
                        }
                        return true;
                    }
                }
            }
            return false;
        }
        foreach ($this->endpointsSupport[$type] as $endpointRegEx) {
            if (preg_match('/' . $endpointRegEx . '/', $endpoint)) {
                return true;
            }
        }
        return false;
    }
}
