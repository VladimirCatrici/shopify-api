<?php

namespace VladimirCatrici\Shopify;

use VladimirCatrici\Shopify\Webhook\WebhookArrayFormatter;
use VladimirCatrici\Shopify\Webhook\WebhookDataFormatterInterface;

class Webhook
{
    protected static $data;
    private static $hmacSha256;

    /**
     * @return string|null
     */
    public static function getTopic()
    {
        return static::getServerVar('HTTP_X_SHOPIFY_TOPIC');
    }

    /**
     * @return string|null
     */
    public static function getShopDomain()
    {
        return static::getServerVar('HTTP_X_SHOPIFY_SHOP_DOMAIN');
    }

    /**
     * @return string|null
     */
    public static function getApiVersion()
    {
        return static::getServerVar('HTTP_X_SHOPIFY_API_VERSION');
    }

    public static function getData(WebhookDataFormatterInterface $dataFormatter = null)
    {
        if (empty($dataFormatter)) {
            return !empty(self::$data) ? self::$data : self::$data = static::getInputStream();
        }
        return $dataFormatter->output(self::getData());
    }

    public static function getDataAsArray(): array
    {
        $formatter = new WebhookArrayFormatter();
        return self::getData($formatter);
    }

    public static function validate(string $token): bool
    {
        $calculated_hmac = base64_encode(hash_hmac('sha256', self::getData(), $token, true));
        return (static::getHmacSha256() == $calculated_hmac);
    }

    protected static function getInputStream()
    {
        return file_get_contents('php://input');
    }

    /**
     * @return string|null
     */
    public static function getHmacSha256()
    {
        return !empty(self::$hmacSha256) ?
            self::$hmacSha256 : self::$hmacSha256 = static::getServerVar('HTTP_X_SHOPIFY_HMAC_SHA256');
    }

    /**
     * @param $name
     * @return string|null
     */
    private static function getServerVar($name)
    {
        if (filter_has_var(INPUT_SERVER, $name)) {
            return filter_input(INPUT_SERVER, $name, FILTER_SANITIZE_STRING);
        } elseif (isset($_SERVER[$name])) {
            return filter_var($_SERVER[$name], FILTER_SANITIZE_STRING);
        }
        return null;
    }
}
