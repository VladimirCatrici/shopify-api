<?php
namespace VladimirCatrici\Shopify;

use VladimirCatrici\Shopify\API\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

class API {

    private $baseUrl;
    private $accessToken;

    public $respCode;
    public $respHeaders;

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
        'max_limit_rate_sleep_sec' => 1
    ];

    /**
     * API constructor.
     * @param string $domain Shopify domain
     * @param string $accessToken
     * @param array $clientOptions GuzzleHttp client options
     */
    public function __construct(string $domain, string $accessToken, array $clientOptions = []) {
        $domain = trim(preg_replace('/\.myshopify\.com$/', '', $domain));
        $this->baseUrl = 'https://' . $domain . '.myshopify.com/admin/';
        $this->accessToken = $accessToken;

        $internalOptions = array_keys($this->options);
        foreach ($internalOptions as $optionName) {
            if (array_key_exists($optionName, $clientOptions)) {
                $this->setOption($optionName, $clientOptions[$optionName]);
                unset($clientOptions[$optionName]);
            }
        }

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $this->accessToken
            ]
        ] + $clientOptions);
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
        $this->options[$key] = $value;
    }
}
