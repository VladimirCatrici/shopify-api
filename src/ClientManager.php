<?php


namespace VladimirCatrici\Shopify;


use InvalidArgumentException;

class ClientManager {

    /**
     * This variable contains a list of configurations (array) or initialized clients (instance of API).
     * Array configuration is kept unless the API client is requested by get() method.
     * Once requested - it initializes the API client with that configuration and replaces the array with the API
     * client object.
     * @var array
     */
    private static $clients;

    public static function setConfig($key, $config = []) {
        self::$clients[$key] = $config;
    }

    /**
     * Returns a configured Shopify API client
     * @param $key
     * @return API
     * @throws InvalidArgumentException
     */
    public static function get($key) {
        if (!isset(self::$clients[$key])) {
            throw new InvalidArgumentException(sprintf(
                'Shopify API client configuration not found with such a key: "%s"', $key)
            );
        }
        if (is_array(self::$clients[$key])) { // Initialize the API client (if necessary)
            $config = self::$clients[$key];
            self::$clients[$key] = new API($config['domain'], $config['access_token'], $config);
        }
        return self::$clients[$key];
    }
}
