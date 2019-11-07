<?php


namespace VladimirCatrici\Shopify;


use DateTime;
use DateTimeZone;
use Exception;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\StreamInterface;
use VladimirCatrici\Shopify\Exception\RequestException;

class Client {
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

    public function __construct(ClientConfig $config) {
        $this->config = $config;
        $this->initClient();
    }

    private function initClient(): void {
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $this->config->getBaseUrl(),
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $this->config->getAccessToken()
            ]
        ] + $this->config->getHttpClientOptions());
    }

    /**
     * @param $endpoint
     * @param array $query
     * @return mixed|StreamInterface
     * @throws RequestException
     */
    public function get($endpoint, $query = []) {
        return $this->request('get', $endpoint, $query);
    }

    /**
     * @param $endpoint
     * @param array $data
     * @return mixed|StreamInterface
     * @throws RequestException
     */
    public function post($endpoint, $data = []) {
        return $this->request('post', $endpoint, null, $data);
    }

    /**
     * @param $endpoint
     * @param array $data
     * @return mixed|StreamInterface
     * @throws RequestException
     */
    public function put($endpoint, $data = []) {
        return $this->request('put', $endpoint, null, $data);
    }

    /**
     * @param $endpoint
     * @return mixed|StreamInterface
     * @throws RequestException
     */
    public function delete($endpoint) {
        return $this->request('delete', $endpoint);
    }/** @noinspection PhpUndefinedClassInspection */
    /** @noinspection PhpDocMissingThrowsInspection */

    /**
     * @param $method
     * @param $endpoint
     * @param array $query
     * @param array $data
     * @return mixed|StreamInterface
     * @throws RequestException
     */
    private function request($method, $endpoint, $query = [], $data = []) {
        $fullApiRequestURL = $this->generateFullApiRequestURL($endpoint, $query);

        $maxAttemptsOnServerErrors      = $this->config->getMaxAttemptsOnServerErrors();
        $maxAttemptsOnRateLimitErrors   = $this->config->getMaxAttemptsOnRateLimitErrors();

        $serverErrors = 0;
        $rateLimitErrors = 0;
        $lastException = null;
        $options = [];
        if (in_array($method, ['put', 'post']) && !empty($data)) {
            $options = [
                'json' => $data
            ];
        }
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

        $this->respCode = $response->getStatusCode();
        $this->respHeaders = $response->getHeaders();
        $apiVersionHeaderKey = 'X-Shopify-API-Version';
        if (is_array($this->respHeaders) && array_key_exists($apiVersionHeaderKey, $this->respHeaders)) {
            $this->config->setApiVersion($this->respHeaders[$apiVersionHeaderKey][0]);
        }
        $this->rateLimitSleepIfNeeded($response);

        // Response Body
        $body = $response->getBody()->getContents();
        return $this->config->getResponseFormatter()->output($body);
    }

    /**
     * @param $guzzleRequestException \GuzzleHttp\Exception\RequestException
     * @return array
     */
    private function handleRequestException($guzzleRequestException) {
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
            $limit = explode('/', $response->getHeaderLine('X-Shopify-Shop-Api-Call-Limit'));
            $rate = $limit[0] / $limit[1];
            if ($rate > $this->config->getMaxLimitRate()) {
                sleep($this->config->getMaxLimitRateSleepSeconds());
            }
        }
    }

    /**
     * @param mixed $date DateTime string (YYYY-MM[...]) or DateTime object
     * @return string in a YYYY-MM format
     * @throws Exception if DateTime object could not be initialized with the provided $date
     */
    public static function getOldestSupportedVersion($date = null) {
        if (is_string($date)) {
            $dt = new DateTime($date);
        } elseif ($date instanceof DateTime) {
            $dt = $date;
        } else {
            $dt = new DateTime();
        }
        $tz = new DateTimeZone('UTC');
        $dt->setTimezone($tz);
        $currentYearMonth = $dt->format('Y-m');
        if ($currentYearMonth < '2020-04') {
            return '2019-04';
        }
        $currentYearMonthParts = explode('-', $currentYearMonth);
        $currentYear = $currentYearMonthParts[0];
        $currentMonth = $currentYearMonthParts[1];

        $monthMapping = [
            '01' => '04', '02' => '04', '03' => '04',
            '04' => '07', '05' => '07', '06' => '07',
            '07' => '10', '08' => '10', '09' => '10',
            '10' => '01', '11' => '01', '12' => '01'
        ];
        $returnMonth = $monthMapping[$currentMonth];

        // If the latest supported version has been released in April or later,
        // then current date is Jan-Sep and that means that it was released in the previous year.
        // Still the same year for any date withing Oct-Dec range.
        return $currentYear - ($returnMonth >= '04' ? 1 : 0) . '-' . $returnMonth;
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