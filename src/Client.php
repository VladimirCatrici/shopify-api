<?php

declare(strict_types=1);

namespace VladimirCatrici\Shopify;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\StreamInterface;
use VladimirCatrici\Shopify\Exception\RequestException;

class Client implements ClientInterface
{
    /**
     * @var ClientConfig
     */
    private $config;
    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;
    /**
     * @var integer Last request response code
     */
    public $respCode;
    /**
     * @var array Last request response headers
     */
    public $respHeaders;

    public function __construct(ClientConfig $config)
    {
        $this->config = $config;
        $this->initClient();
    }

    protected function initClient(): void
    {
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $this->config->getBaseUrl(),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $this->config->getAccessToken()
            ]
        ]);
    }

    /**
     * @param string $endpoint
     * @param array $query
     * @return mixed
     * @throws RequestException
     */
    public function get(string $endpoint, array $query = [])
    {
        return $this->request('get', $endpoint, $query);
    }

    /**
     * @param $endpoint
     * @param array $data
     * @return mixed
     * @throws RequestException
     */
    public function post(string $endpoint, array $data = [])
    {
        return $this->request('post', $endpoint, null, $data);
    }

    /**
     * @param $endpoint
     * @param array $data
     * @return mixed
     * @throws RequestException
     */
    public function put(string $endpoint, array $data = [])
    {
        return $this->request('put', $endpoint, null, $data);
    }

    /**
     * @param $endpoint
     * @return mixed
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
     * @return mixed
     * @throws RequestException
     */
    private function request($method, $endpoint, $query = [], $data = [])
    {
        $fullApiRequestURL = $this->generateFullApiRequestURL($endpoint, $query);

        $maxAttemptsOnServerErrors = $this->config->getMaxAttemptsOnServerErrors();
        $maxAttemptsOnRateLimitErrors = $this->config->getMaxAttemptsOnRateLimitErrors();

        $serverErrors = 0;
        $rateLimitErrors = 0;
        $lastException = null;

        // Set data as a value of `json` attribute if it's a `post` or `put` request.
        $options = in_array($method, ['put', 'post']) && !empty($data) ? ['json' => $data] : [];

        if ($this->config->isSensitivePropertyChanged()) {
            $this->initClient();
            $this->config->setSensitivePropertyChanged(false);
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

        $this->requestCallback($response);

        // Format and return response
        $body = $response->getBody()->getContents();
        return $this->config->getResponseFormatter()->output($body);
    }

    /**
     * @param $guzzleRequestException \GuzzleHttp\Exception\RequestException
     * @return array
     */
    private function handleRequestException($guzzleRequestException): array
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
                    sleep((int) $guzzleRequestException->getResponse()->getHeaderLine('Retry-After'));
                    break;
                }
                sleep(1);
                break;
            default:
                $output['break'] = true;
        }
        return $output;
    }

    /**
     * @param Response $response
     */
    private function requestCallback(Response $response): void
    {
        $this->respCode = $response->getStatusCode();
        $this->respHeaders = $response->getHeaders();
        $this->rateLimitSleepIfNeeded($response);

        $apiVersionHeaderKey = 'X-Shopify-API-Version';
        if (is_array($this->respHeaders) && array_key_exists($apiVersionHeaderKey, $this->respHeaders)) {
            $this->config->setApiVersion($this->respHeaders[$apiVersionHeaderKey][0]);
        }
    }

    private function generateFullApiRequestURL($endpoint, $queryParams = []): string
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

    private function rateLimitSleepIfNeeded(Response $response): void
    {
        if ($response->hasHeader('X-Shopify-Shop-Api-Call-Limit')) {
            $limit = explode('/', $response->getHeaderLine('X-Shopify-Shop-Api-Call-Limit'));
            $rate = $limit[0] / $limit[1];
            if ($rate > $this->config->getMaxLimitRate()) {
                sleep((int) $this->config->getMaxLimitRateSleepSeconds());
            }
        }
    }

    /**
     * @return ClientConfig
     */
    public function getConfig(): ClientConfig
    {
        return $this->config;
    }

    /**
     * @param ClientConfig $config
     */
    public function setConfig(ClientConfig $config): void
    {
        $this->config = $config;
    }
}
