<?php

namespace VladimirCatrici\Shopify;

use Exception;

abstract class PaginationType {
    public const NOT_REQUIRED = 0;
    public const CURSOR = 1;
    public const SINCE = 2;
    public const PAGE = 3;

    private static $endpointsSupport = [
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
     * Detect pagination type to use for specific endpoint / api version.
     * @param string $endpoint API endpoint e.g. "products" or "blogs/123456/articles"
     * @param string $apiVersion API version to match against
     * @return int One of the pagination types e.g. PaginationType::CURSOR or PaginationType::SINCE
     * @throws Exception
     */
    public static function detect(string $endpoint, string $apiVersion = null): int {
        if (in_array($endpoint, self::$endpointsSupport['no_pagination'])) {
            return self::NOT_REQUIRED;
        }

        if ($apiVersion >= '2019-07') {
            foreach (self::$endpointsSupport['cursor'] as $version => $endpointsRegEx) {
                if ($version > $apiVersion) {
                    continue;
                }
                foreach ($endpointsRegEx as $re) {
                    if (!preg_match('/' . $re . '/', $endpoint)) {
                        continue;
                    }
                    return self::CURSOR;
                }
            }
        }

        foreach (self::$endpointsSupport['since'] as $endpointRegEx) {
            if (preg_match('/' . $endpointRegEx . '/', $endpoint)) {
                return PaginationType::SINCE;
            }
        }

        foreach (self::$endpointsSupport['page'] as $endpointRegEx) {
            if (preg_match('/' . $endpointRegEx . '/', $endpoint)) {
                return PaginationType::PAGE;
            }
        }

        throw new Exception(sprintf('Pagination type is not defined for `%s` endpoint', $endpoint));
    }
}
