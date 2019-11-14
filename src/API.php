<?php

namespace VladimirCatrici\Shopify;

use Exception;
use VladimirCatrici\Shopify\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use VladimirCatrici\Shopify\Response\ResponseArrayFormatter;
use VladimirCatrici\Shopify\Response\ResponseDataFormatterInterface;

/**
 * Class API
 * @package VladimirCatrici\Shopify
 * @deprecated This class is deprecated since v0.2.0 and will be removed in v0.3.0. Use Client class instead
 */
class API implements ClientInterface
{
    /**
     * @var string Shopify store handle. If you shop permanent domain is test.myshopify.com - the "test" is the handle
     * in this case
     */
    private $handle;
    /**
     * @var string Full URL to the API including protocol and api API version e.g.
     * https://test.myshopify.com/admin/api/2019-10/
     */
    private $baseUrl;
    /**
     * @var string
     */
    private $accessToken;
    /**
     * @var integer Last request response code
     */
    public $respCode;
    /**
     * @var array Last request response headers
     */
    public $respHeaders;
    /**
     * @var Client
     */
    private $client;
    /**
     * @var ResponseDataFormatterInterface
     */
    private $responseFormatter;
    private $options = [
        /**
         * @var int Number of attempts trying to execute the request.
         * It's useful because sometimes Shopify may respond with 500 error.
         * The recommended value is 2 or 3
         */
        'max_attempts_on_server_errors' => 1,

        /**
         * @var int Number of attempts trying to execute the request on getting 429 Too Many Connections error.
         * This might be useful if the same API key is used by other apps which may lead to exceeding the rate limit.
         * The recommended value would be somewhere between would be up to 10
         */
        'max_attempts_on_rate_limit_errors' => 1,

        /**
         * @var float Number between 0 and 1 describing the maximum limit rate the client should reach before going
         * sleep. See `max_limit_rate_sleep_sec` option
         */
        'max_limit_rate' => 0.5,

        /**
         * @var int Number of seconds to sleep when API reaches the maximum API limit rate specified in `max_limit_rate`
         * option
         */
        'max_limit_rate_sleep_sec' => 1,

        /**
         * @var string API version, expecting format is YYYY-MM. By default uses the oldest supported stable version.
         */
        'api_version' => null,

        /**
         * @var string Response data formatter class name
         */
        'response_data_formatter' => null
    ];

    /**
     * API constructor.
     * @param string $domain Shopify domain
     * @param string $accessToken
     * @param array $clientOptions GuzzleHttp client options
     */
    public function __construct(string $domain, string $accessToken, array $clientOptions = [])
    {
        $this->handle = trim(preg_replace('/\.myshopify\.com$/', '', $domain));
        $this->accessToken = $accessToken;

        $internalOptions = array_keys($this->options);
        foreach ($internalOptions as $optionName) {
            if (array_key_exists($optionName, $clientOptions)) {
                $this->setOption($optionName, $clientOptions[$optionName]);
                unset($clientOptions[$optionName]);
            }
        }

        $this->baseUrl = 'https://' . $this->handle . '.myshopify.com/admin/' . (
            !empty($this->options['api_version']) ? 'api/' . $this->options['api_version'] . '/' : '');
        $client = new Client([
                'base_uri' => $this->baseUrl,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Shopify-Access-Token' => $this->accessToken
                ]
            ] + $clientOptions);
        $this->setClient($client);

        // Initialize default response data formatter (if necessary)
        if (empty($this->responseFormatter)) {
            $this->responseFormatter = new ResponseArrayFormatter();
        }
    }

    /**
     * @param Client $client
     */
    private function setClient($client)
    {
        $this->client = $client;
    }

    /**
     * @param string $endpoint
     * @param array $query
     * @return mixed|StreamInterface
     * @throws RequestException
     */
    public function get(string $endpoint, array $query = [])
    {
        return $this->request('get', $endpoint, $query);
    }

    /**
     * @param string $endpoint
     * @param array $data
     * @return mixed|StreamInterface
     * @throws RequestException
     */
    public function post(string $endpoint, array $data = [])
    {
        return $this->request('post', $endpoint, null, $data);
    }

    /**
     * @param string $endpoint
     * @param array $data
     * @return mixed|StreamInterface
     * @throws RequestException
     */
    public function put(string $endpoint, array $data = [])
    {
        return $this->request('put', $endpoint, null, $data);
    }

    /**
     * @param string $endpoint
     * @return mixed|StreamInterface
     * @throws RequestException
     */
    public function delete(string $endpoint)
    {
        return $this->request('delete', $endpoint);
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     * @param $method
     * @param $endpoint
     * @param array $query
     * @param array $data
     * @return mixed|StreamInterface
     * @throws RequestException
     */
    private function request($method, $endpoint, $query = [], $data = [])
    {
        $fullApiRequestURL = $this->generateFullApiRequestURL($endpoint, $query);

        $maxAttemptsOnServerErrors = $this->getOption('max_attempts_on_server_errors');
        $maxAttemptsOnRateLimitErrors = $this->getOption('max_attempts_on_rate_limit_errors');

        $serverErrors = 0;
        $rateLimitErrors = 0;
        $lastException = null;
        $options = [];
        if (in_array($method, ['put', 'post']) && !empty($data)) {
            $options = [
                'json' => $data
            ];
        }
        while ($serverErrors < $maxAttemptsOnServerErrors && $rateLimitErrors < $maxAttemptsOnRateLimitErrors) {
            try {
                /** @noinspection PhpUnhandledExceptionInspection */
                $response = $this->client->request($method, $fullApiRequestURL, $options);
                break;
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $lastException = new RequestException($this->client, $e);
                $handlerResult = $this->handleRequestException($e);
                if ($handlerResult['break']) {
                    break;
                }
                $serverErrors += $handlerResult['server_error'];
                $rateLimitErrors += $handlerResult['rate_limit_error'];
            }
        }

        if (empty($response)) {
            throw $lastException;
        }

        $this->respCode = $response->getStatusCode();
        $this->respHeaders = $response->getHeaders();
        $this->rateLimitSleepIfNeeded($response);

        // Response Body
        $body = $response->getBody()->getContents();
        return $this->responseFormatter->output($body);
    }

    /**
     * @param $guzzleRequestException \GuzzleHttp\Exception\RequestException
     * @return array
     */
    private function handleRequestException($guzzleRequestException)
    {
        $output = [
            'server_error' => 0,
            'rate_limit_error' => 0,
            'break' => false
        ];
        switch ($guzzleRequestException->getResponse()->getStatusCode()) {
            case 500:
            case 503:
            case 504:
                $output['server_error'] = 1;
                break;
            case 429:
                $output['rate_limit_error'] = 1;
                if ($guzzleRequestException->getResponse()->hasHeader('Retry-After')) {
                    sleep($guzzleRequestException->getResponse()->getHeaderLine('Retry-After'));
                    break;
                }
                sleep(1);
                break;
            default:
                $output['break'] = true;
        }
        return $output;
    }

    private function generateFullApiRequestURL($endpoint, $queryParams = [])
    {
        if (!preg_match('/\.json$/', $endpoint)) {
            $endpoint .= '.json';
        }
        if (!empty($queryParams)) {
            $queryString = http_build_query($queryParams);
            return $endpoint . '?' . $queryString;
        }
        return $endpoint;
    }

    private function rateLimitSleepIfNeeded(Response $response)
    {
        if ($response->hasHeader('X-Shopify-Shop-Api-Call-Limit')) {
            $limit = explode('/', $response->getHeaderLine('X-Shopify-Shop-Api-Call-Limit'));
            $rate = $limit[0] / $limit[1];
            if ($rate > $this->getOption('max_limit_rate')) {
                sleep($this->getOption('max_limit_rate_sleep_sec'));
            }
        }
    }

    public function getOption($key)
    {
        if (!array_key_exists($key, $this->options)) {
            throw new InvalidArgumentException(sprintf('Trying to get an invalid option `%s`', $key));
        }
        return $this->options[$key];
    }

    public function setOption($key, $value)
    {
        if (!array_key_exists($key, $this->options)) {
            throw new InvalidArgumentException(sprintf('Trying to set an invalid option `%s`', $key));
        }

        if ($key == 'api_version') {
            $value = trim($value);
            $this->validateApiVersion($value);

            // Update client config
            if (!empty($this->client)) {
                $currentClientConfig = $this->client->getConfig();
                $this->baseUrl = preg_replace(
                    '/admin\/(api\/\d{4}-\d{2}\/)?$/',
                    'admin/api/' . $value . '/',
                    $this->baseUrl
                );
                $currentClientConfig['base_uri'] = $this->baseUrl;
                $this->setClient(new Client($currentClientConfig));
            }
        }

        if ($key == 'response_data_formatter') {
            $this->responseFormatter = new $value();
        }

        $this->options[$key] = $value;
    }

    private function validateApiVersion($value)
    {
        $readMoreAboutApiVersioning = 'Read more about versioning here: https://help.shopify.com/en/api/versioning';
        if (!preg_match('/^(\d{4})-(\d{2})$/', $value, $matches)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid API version format: "%s". The "YYYY-MM" format expected. ' . $readMoreAboutApiVersioning,
                    $value
                )
            );
        }
        if ($matches[1] < 2019) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid API version year: "%s". The API versioning has been released in 2019. ' . $readMoreAboutApiVersioning,
                    $value
                )
            );
        }
        if (!preg_match('/01|04|07|10/', $matches[2])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid API version month: "%s". 
                    The API versioning has been released on April 2019 and new releases scheduled every 3 months, 
                    so only "01", "04", "07" and "10" expected as a month. Otherwise, 
                    "404 Not Found" will be returned by Shopify. ' . $readMoreAboutApiVersioning,
                    $matches[0]
                )
            );
        }
    }

    public function setVersion($version)
    {
        $this->setOption('api_version', $version);
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getVersion()
    {
        $version = $this->getOption('api_version');
        if (empty($version)) {
            $headerKey = 'X-Shopify-API-Version';
            if (is_array($this->respHeaders) && array_key_exists($headerKey, $this->respHeaders)) {
                $this->options['api_version'] = $this->respHeaders[$headerKey][0];
            } else {
                $this->options['api_version'] = static::getOldestSupportedVersion();
            }
        }
        return $this->getOption('api_version');
    }

    /**
     * @param mixed $date DateTime string (YYYY-MM[...]) or DateTime object
     * @return string
     * @throws Exception
     */
    public static function getOldestSupportedVersion($date = null)
    {
        return getOldestSupportedVersion($date);
    }
}
