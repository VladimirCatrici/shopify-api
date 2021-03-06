<?php

declare(strict_types=1);

namespace VladimirCatrici\Shopify;

use ArrayAccess;
use InvalidArgumentException;
use VladimirCatrici\Shopify\Response\ResponseDefaultFormatter;
use VladimirCatrici\Shopify\Response\ResponseDataFormatterInterface;

/**
 * Class ClientConfig
 * @package VladimirCatrici\Shopify
 */
class ClientConfig implements ArrayAccess
{
    /**
     * @var string
     */
    private $handle = '';
    /**
     * @var string
     */
    private $permanentDomain = '';
    /**
     * @var string
     */
    private $baseUrl = '';
    /**
     * @var string
     */
    private $accessToken = '';
    /**
     * @var int
     */
    private $maxAttemptsOnServerErrors = 1;
    /**
     * @var int
     */
    private $maxAttemptsOnRateLimitErrors = 1;
    /**
     * @var string
     */
    private $apiVersion = '';
    /**
     * @var float
     */
    private $maxLimitRate = 0.5;
    /**
     * @var int
     */
    private $maxLimitRateSleepSeconds = 1;
    /**
     * @var ResponseDataFormatterInterface
     */
    private $responseFormatter;
    /**
     * @var bool
     */
    protected $sensitivePropertyChanged = false;

    /**
     * ClientConfig constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        foreach ($options as $key => $val) {
            if (!property_exists($this, $key)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid property `%s`', $key)
                );
            }
            $setMethodName = 'set' . ucfirst($key);
            $this->{$setMethodName}($val);
        }
        $this->setHandle($options['handle'] ?? '');
        if (empty($this->responseFormatter)) {
            $this->setResponseFormatter(new ResponseDefaultFormatter());
        }
    }

    /**
     * @return string
     */
    public function getHandle(): string
    {
        return $this->handle;
    }

    /**
     * @return string
     */
    public function getPermanentDomain(): string
    {
        return $this->permanentDomain;
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @param string $handle
     * @return ClientConfig
     */
    public function setHandle(string $handle): self
    {
        if ($this->handle != $handle) {
            $this->handle = $handle;
            $this->permanentDomain = str_replace('.myshopify.com', '', trim($this->handle)) . '.myshopify.com';
            $this->resetBaseUrl();
        }
        return $this;
    }

    private function resetBaseUrl()
    {
        $this->baseUrl = 'https://' . $this->permanentDomain . '/admin/';
        if (!empty($this->apiVersion)) {
            $this->baseUrl .= 'api/' . $this->apiVersion . '/';
        }
        $this->sensitivePropertyChanged = true;
    }

    /**
     * @return string
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * @param string $accessToken
     * @return ClientConfig
     */
    public function setAccessToken(string $accessToken): self
    {
        if ($this->accessToken != $accessToken) {
            $this->accessToken = $accessToken;
            $this->sensitivePropertyChanged = true;
        }
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxAttemptsOnServerErrors(): int
    {
        return $this->maxAttemptsOnServerErrors;
    }

    /**
     * @param int $maxAttemptsOnServerErrors
     * @return ClientConfig
     */
    public function setMaxAttemptsOnServerErrors(int $maxAttemptsOnServerErrors): self
    {
        $this->maxAttemptsOnServerErrors = $maxAttemptsOnServerErrors;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxAttemptsOnRateLimitErrors(): int
    {
        return $this->maxAttemptsOnRateLimitErrors;
    }

    /**
     * @param int $maxAttemptsOnRateLimitErrors
     * @return ClientConfig
     */
    public function setMaxAttemptsOnRateLimitErrors(int $maxAttemptsOnRateLimitErrors): self
    {
        $this->maxAttemptsOnRateLimitErrors = $maxAttemptsOnRateLimitErrors;
        return $this;
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection as getOldestSupportedVersion may throw exception only if DateTime
     * failed to initialize. This supposed to happen only if an invalid date/time passed to this function. We don't need
     * this inspection as we don't pass any params.
     * @return string
     */
    public function getApiVersion(): string
    {
        if (empty($this->apiVersion)) {
            /** @noinspection PhpUnhandledExceptionInspection */
            return getOldestSupportedVersion();
        }
        return $this->apiVersion;
    }

    /**
     * @param string $apiVersion
     * @return ClientConfig
     */
    public function setApiVersion(string $apiVersion): self
    {
        $this->validateApiVersion($apiVersion);
        $this->apiVersion = $apiVersion;
        $this->resetBaseUrl();
        return $this;
    }

    /**
     * @return float
     */
    public function getMaxLimitRate(): float
    {
        return $this->maxLimitRate;
    }

    /**
     * @param float $maxLimitRate
     * @return ClientConfig
     */
    public function setMaxLimitRate(float $maxLimitRate): self
    {
        $this->maxLimitRate = $maxLimitRate;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxLimitRateSleepSeconds(): int
    {
        return $this->maxLimitRateSleepSeconds;
    }

    /**
     * @param int $maxLimitRateSleepSeconds
     * @return ClientConfig
     */
    public function setMaxLimitRateSleepSeconds(int $maxLimitRateSleepSeconds): self
    {
        $this->maxLimitRateSleepSeconds = $maxLimitRateSleepSeconds;
        return $this;
    }

    /**
     * @return ResponseDataFormatterInterface
     */
    public function getResponseFormatter(): ResponseDataFormatterInterface
    {
        return $this->responseFormatter;
    }

    /**
     * @param ResponseDataFormatterInterface $responseFormatter
     * @return ClientConfig
     */
    public function setResponseFormatter(ResponseDataFormatterInterface $responseFormatter): self
    {
        $this->responseFormatter = $responseFormatter;
        return $this;
    }

    /**
     * @param $apiVersion
     */
    private function validateApiVersion($apiVersion)
    {
        $readMoreAboutApiVersioning = 'Read more about versioning here: https://help.shopify.com/en/api/versioning';
        if (!preg_match('/^(\d{4})-(\d{2})$/', $apiVersion, $matches)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid API version format: "%s". The "YYYY-MM" format expected. ' . $readMoreAboutApiVersioning,
                    $apiVersion
                )
            );
        }
        if ($matches[1] < 2019) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid API version year: "%s". The API versioning has been released in 2019. ' .
                    $readMoreAboutApiVersioning,
                    $apiVersion
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

    /**
     * @return bool
     */
    public function isSensitivePropertyChanged(): bool
    {
        return $this->sensitivePropertyChanged;
    }

    /**
     * @param bool $sensitivePropertyChanged
     */
    public function setSensitivePropertyChanged(bool $sensitivePropertyChanged): void
    {
        $this->sensitivePropertyChanged = $sensitivePropertyChanged;
    }

    /**
     * Whether a offset exists
     * @link https://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset An offset to check for.
     * @return bool true on success or false on failure.
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return property_exists($this, $offset);
    }

    /**
     * Offset to retrieve
     * @link https://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset The offset to retrieve.
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        return $this->{'get' . ucfirst($offset)}();
    }

    /**
     * Offset to set
     * @link https://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value The value to set.
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        $this->{'set' . ucfirst($offset)}($value);
    }

    /**
     * Offset to unset
     * @link https://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset The offset to unset.
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        $props = get_class_vars(ClientConfig::class);
        $this->{'set' . ucfirst($offset)}($props[$offset]);
    }
}
