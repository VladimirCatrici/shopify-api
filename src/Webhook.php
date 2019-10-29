<?php
namespace VladimirCatrici\Shopify;

use VladimirCatrici\Shopify\Webhook\WebhookArrayFormatter;
use VladimirCatrici\Shopify\Webhook\WebhookDataFormatterInterface;

class Webhook {

    protected static $data;

    private static $hmacSha256;

    public static function getTopic(): string {
        return !empty($_SERVER['HTTP_X_SHOPIFY_TOPIC']) ?
            filter_var($_SERVER['HTTP_X_SHOPIFY_TOPIC'], FILTER_SANITIZE_STRING) : '';
    }

    public static function getShopDomain(): string {
        return !empty($_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN']) ?
            filter_var($_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'], FILTER_SANITIZE_STRING) : '';
    }

    public static function getApiVersion(): string {
        return !empty($_SERVER['HTTP_X_SHOPIFY_API_VERSION']) ?
            filter_var($_SERVER['HTTP_X_SHOPIFY_API_VERSION'], FILTER_SANITIZE_STRING) : '';
    }

    public static function getData(WebhookDataFormatterInterface $dataFormatter = null) {
        if (empty($dataFormatter)) {
            return !empty(self::$data) ? self::$data : self::$data = static::getInputStream();
        } else {
            return $dataFormatter->output(self::getData());
        }
    }

    public static function getDataAsArray() : array {
        $formatter = new WebhookArrayFormatter();
        return self::getData($formatter);
    }

    public static function validate(string $token) : bool {
        $calculated_hmac = base64_encode(hash_hmac('sha256', self::getData(), $token, true));
        return (self::getHmacSha256() == $calculated_hmac);
    }

    protected static function getInputStream() {
        return file_get_contents('php://input');
    }

    private static function getHmacSha256(): string {
        return !empty(self::$hmacSha256) ?
            self::$hmacSha256 : self::$hmacSha256 =
                filter_var($_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'], FILTER_SANITIZE_STRING) ?: '';
    }
}
