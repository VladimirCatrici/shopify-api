<?php

namespace ShopifyAPI\Tests;

use VladimirCatrici\Shopify\Webhook;

class WebhookTestDouble extends Webhook {
    protected static $inputStream;

    private static $topic;

    private static $shopDomain;

    private static $hmacSha256;

    /**
     * @param string $hmacSha256
     */
    public static function setHmacSha256($hmacSha256) {
        static::$hmacSha256 = $hmacSha256;
    }

    /**
     * @param string $shopDomain
     */
    public static function setShopDomain($shopDomain) {
        static::$shopDomain = $shopDomain;
    }

    /**
     * @param string $topic
     */
    public static function setTopic($topic) {
        static::$topic = $topic;
    }

    /**
     * @return mixed
     */
    public static function getTopic() {
        return self::$topic;
    }

    /**
     * @return mixed
     */
    public static function getShopDomain() {
        return self::$shopDomain;
    }

    /**
     * @return mixed
     */
    public static function getHmacSha256() {
        return self::$hmacSha256;
    }

    /**
     * @param string $input
     */
    public static function setInputStream($input = '') {
        static::$inputStream = $input;
    }

    /**
     * @return false|string
     */
    protected static function getInputStream() {
        return static::$inputStream;
    }

    /**
     * As this is static class we need to have an ability to clear the $data variable to be able run test multiple times.
     */
    public static function clearData() {
        parent::$data = null;
    }
}
