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

    /**
     * Configure a new Shopify client.
     *
     * The first parameter is a key to save configuration against.
     * The second parameter can consists the following options:
     *
     * * _int_ **max_attempts_on_server_errors** - number of attempts trying to execute the request.
     * It's useful because sometimes Shopify may respond with 500 error.
     * The recommended value is 2 or 3. Default value is: **1**
     *
     * * _int_ **max_attempts_on_rate_limit_errors** - number of attempts trying to execute the request on getting
     * 429 Too Many Connections error. This might be useful if the same API key is used by other apps which may lead to
     * exceeding the rate limit. The recommended value would be somewhere between would be up to 10. Default value is
     * set to **1** though.
     *
     * * _float_ **max_limit_rate** - number between 0 and 1 describing the maximum limit rate the client should reach
     * before going sleep. See `max_limit_rate_sleep_sec` option. Default value is set to **0.5**
     *
     * * _int_ **max_limit_rate_sleep_sec** - number of seconds to sleep when API reaches the maximum API limit rate
     * specified in `max_limit_rate` option. Default: **1**
     *
     * * _string_ **api_version** - API version, expecting format is YYYY-MM. Default value: _null_ - will be used the
     * oldest supported version
     *
     * In addition to the options above it an also accept any option to configure
     * [Guzzle HTTP Client](http://docs.guzzlephp.org/en/stable/quickstart.html?highlight=client), except `base_uri`
     * and `headers`
     *
     * @param string $key
     * @param array $config An associative array with client configuration
     * @return void
     */
    public static function setConfig(string $key, $config = []) {
        self::$clients[$key] = $config;
    }

    /**
     * Returns a configured Shopify API client
     *
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
