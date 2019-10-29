<?php

namespace ShopifyAPI\Tests;

use VladimirCatrici\Shopify\Webhook;

class WebhookTestDouble extends Webhook {
    protected static $inputStream;

    public static function setInputStream($input = '') {
        static::$inputStream = $input;
    }

    protected static function getInputStream() {
        return static::$inputStream;
    }

    public static function clearData() {
        parent::$data = null;
    }
}
