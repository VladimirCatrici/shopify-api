<?php
namespace VladimirCatrici\Shopify;

use VladimirCatrici\Shopify\API\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

class API {

    private $handle;
    private $baseUrl;
    private $accessToken;

    public $respCode;
    public $respHeaders;
    /**
     * @var Client
     */
    private $client;

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
         * @var string API version, expecting format is YYYY-MM
         */
        'api_version' => null
    ];

    /**
     * API constructor.
     * @param string $domain Shopify domain
     * @param string $accessToken
     * @param array $clientOptions GuzzleHttp client options
     */
    public function __construct(string $domain, string $accessToken, array $clientOptions = []) {
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
    }

    /**
     * @param Client $client
     */
    private function setClient($client) {
        $this->client = $client;
    }

    /**
     * @param $endpoint
     * @param array $query
     * @return mixed|StreamInterface
     * @throws GuzzleException
     * @throws RequestException
     */
    public function get($endpoint, $query = []) {
        return $this->request('get', $endpoint, $query);
    }

    /**
     * @param $endpoint
     * @param array $data
     * @return mixed|StreamInterface
     * @throws GuzzleException
     * @throws RequestException
     */
    public function post($endpoint, $data = []) {
        return $this->request('post', $endpoint, null, $data);
    }

    /**
     * @param $endpoint
     * @param array $data
     * @return mixed|StreamInterface
     * @throws GuzzleException
     * @throws RequestException
     */
    public function put($endpoint, $data = []) {
        return $this->request('put', $endpoint, null, $data);
    }

    /**
     * @param $endpoint
     * @return mixed|StreamInterface
     * @throws GuzzleException
     * @throws RequestException
     */
    public function delete($endpoint) {
        return $this->request('delete', $endpoint);
    }

    /**
     * @param $method
     * @param $endpoint
     * @param array $query
     * @param array $data
     * @return mixed|StreamInterface
     * @throws GuzzleException
     * @throws RequestException
     */
    private function request($method, $endpoint, $query = [], $data = []) {
        $fullApiRequestURL = $this->generateFullApiRequestURL($endpoint, $query);

        $maxAttemptsOnServerErrors = $this->getOption('max_attempts_on_server_errors');
        $maxAttemptsOnRateLimitErrors = $this->getOption('max_attempts_on_rate_limit_errors');
        $serverErrors = 0;
        $rateLimitErrors = 0;
        $lastException = null;
        while ($serverErrors < $maxAttemptsOnServerErrors && $rateLimitErrors < $maxAttemptsOnRateLimitErrors) {
            try {
                $options = [];
                if (in_array($method, ['put', 'post']) && !empty($data)) {
                    $options = [
                        'json' => $data
                    ];
                }
                $response = $this->client->request($method, $fullApiRequestURL, $options);
                break;
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $lastException = new RequestException($this->client, $e);
                $eResponse = $e->getResponse();
                $respCode = $eResponse->getStatusCode();
                if ($respCode >= 500) {
                    $serverErrors++;
                } elseif ($respCode == 429) {
                    $rateLimitErrors++;
                    if ($eResponse->hasHeader('Retry-After')) {
                        sleep($eResponse->getHeader('Retry-After')[0]);
                    } else {
                        sleep(1);
                    }
                } else {
                    break;
                }
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
        $body = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
        if (is_array($body) && !empty(key($body))) {
            return $body[key($body)];
        }
        return $body;
    }

    private function generateFullApiRequestURL($endpoint, $queryParams = []) {
        if (!preg_match('/\.json$/', $endpoint)) {
            $endpoint .= '.json';
        }
        if (!empty($queryParams)) {
            $queryString = http_build_query($queryParams);
            return $endpoint . '?' . $queryString;
        }
        return $endpoint;
    }

    private function rateLimitSleepIfNeeded(Response $response) {
        if ($response->hasHeader('X-Shopify-Shop-Api-Call-Limit')) {
            $headerValues = $response->getHeader('X-Shopify-Shop-Api-Call-Limit');
            $limit = explode('/', $headerValues[0]);
            $rate = $limit[0] / $limit[1];
            if ($rate > $this->getOption('max_limit_rate')) {
                sleep($this->getOption('max_limit_rate_sleep_sec'));
            }
        }
    }

    public function getOption($key) {
        if (!array_key_exists($key, $this->options)) {
            throw new InvalidArgumentException(sprintf('Trying to get an invalid option `%s`', $key));
        }
        return $this->options[$key];
    }

    public function setOption($key, $value) {
        if (!array_key_exists($key, $this->options)) {
            throw new InvalidArgumentException(sprintf('Trying to set an invalid option `%s`', $key));
        }

        if ($key == 'api_version') {
            $value = trim($value);
            $this->validateApiVersion($value);

            // Update client config
            if (!empty($this->client)) {
                $currentClientConfig = $this->client->getConfig();
                $this->baseUrl = preg_replace('/admin\/(api\/\d{4}-\d{2}\/)?$/', 'admin/api/' . $value . '/', $this->baseUrl);
                $currentClientConfig['base_uri'] = $this->baseUrl;
                $this->setClient(new Client($currentClientConfig));
            }
        }

        $this->options[$key] = $value;
    }

    private function validateApiVersion($value) {
        $readMoreAboutApiVersioning = 'Read more about versioning here: https://help.shopify.com/en/api/versioning';
        if (!preg_match('/^(\d{4})-(\d{2})$/', $value, $matches)) {
            throw new InvalidArgumentException(
                sprintf('Invalid API version format: "%s". The "YYYY-MM" format expected. ' . $readMoreAboutApiVersioning,
                    $value)
            );
        }
        if ($matches[1] < 2019) {
            throw new InvalidArgumentException(
                sprintf('Invalid API version year: "%s". The API versioning has been released in 2019. ' . $readMoreAboutApiVersioning,
                    $value)
            );
        }
        if (!preg_match('/01|04|07|10/', $matches[2])) {
            throw new InvalidArgumentException(
                sprintf('Invalid API version month: "%s". 
                    The API versioning has been released on April 2019 and new releases scheduled every 3 months, 
                    so only "01", "04", "07" and "10" expected as a month. Otherwise, 
                    "404 Not Found" will be returned by Shopify. ' . $readMoreAboutApiVersioning,
                        $matches[0])
            );
        }
    }

    public function setVersion($version) {
        $this->setOption('api_version', $version);
    }

    public function getVersion() {
        $version = $this->getOption('api_version');
        if (empty($version)) {
            $headerKey = 'X-Shopify-API-Version';
            if (is_array($this->respHeaders) && array_key_exists($headerKey, $this->respHeaders)) {
                $this->options['api_version'] = $this->respHeaders[$headerKey][0];
            } else {
                $this->get('shop');
                if (array_key_exists($headerKey, $this->respHeaders)) {
                    $this->options['api_version'] = $this->respHeaders[$headerKey][0];
                }
            }
        }
        return $this->getOption('api_version');
    }
}