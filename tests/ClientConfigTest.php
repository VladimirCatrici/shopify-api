<?php

namespace ShopifyAPI\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use VladimirCatrici\Shopify\ClientConfig;
use VladimirCatrici\Shopify\Response\ResponseDefaultFormatter;

class ClientConfigTest extends TestCase
{


    /**
     * @var ClientConfig
     */
    private static $cfg;
    
    public static function setUpBeforeClass()
    {
        require_once dirname(__FILE__) . '/TestResponseDataFormatter.php';
        self::$cfg = new ClientConfig([
            'handle' => 'test-handle',
            'accessToken' => 'test-access-token',
            'maxAttemptsOnServerErrors' => 5,
            'maxAttemptsOnRateLimitErrors' => 10,
            'apiVersion' => '2019-10',
            'maxLimitRate' => 0.75,
            'maxLimitRateSleepSeconds' => 2
        ]);
    }

    public function testSetHandleInTheConstructorGeneratesPermanentDomain()
    {
        $cfg = new ClientConfig([
            'handle' => 'demo-domain'
        ]);
        $this->assertSame('demo-domain.myshopify.com', $cfg->getPermanentDomain());
    }

    public function testSetHandleInTheConstructorGeneratesBaseUrl()
    {
        $cfg = new ClientConfig([
            'handle' => 'dummy-domain'
        ]);
        $this->assertSame('https://dummy-domain.myshopify.com/admin/', $cfg->getBaseUrl());
    }

    public function testSetApiVersionInTheConstructorGeneratesCorrectBaseUrl()
    {
        $cfg = new ClientConfig([
            'handle' => 'just-a-domain',
            'apiVersion' => '2019-07'
        ]);
        $this->assertSame('https://just-a-domain.myshopify.com/admin/api/2019-07/', $cfg->getBaseUrl());
    }

    public function testSetAccessToken()
    {
        $cfg = new ClientConfig([
            'accessToken' => 'abcdef01234567890'
        ]);
        $this->assertSame('abcdef01234567890', $cfg->getAccessToken());
    }

    public function testPassingArrayToConsructor()
    {
        $this->assertSame('test-handle', self::$cfg->getHandle());
        $this->assertSame('test-handle.myshopify.com', self::$cfg->getPermanentDomain());
        $this->assertSame('https://test-handle.myshopify.com/admin/api/2019-10/', self::$cfg->getBaseUrl());
        $this->assertSame('test-access-token', self::$cfg->getAccessToken());
        $this->assertSame(5, self::$cfg->getMaxAttemptsOnServerErrors());
        $this->assertSame(10, self::$cfg->getMaxAttemptsOnRateLimitErrors());
        $this->assertSame('2019-10', self::$cfg->getApiVersion());
        $this->assertSame(0.75, self::$cfg->getMaxLimitRate());
        $this->assertSame(2, self::$cfg->getMaxLimitRateSleepSeconds());
        $this->assertInstanceOf(ResponseDefaultFormatter::class, self::$cfg->getResponseFormatter());
    }

    public function testChangingConfigOption()
    {
        self::$cfg->setMaxAttemptsOnServerErrors(100)
            ->setMaxAttemptsOnRateLimitErrors(200)
            ->setApiVersion('2019-07')
            ->setMaxLimitRate(0.95)
            ->setMaxLimitRateSleepSeconds(3)
            ->setResponseFormatter(new TestResponseDataFormatter())
            ->setHttpClientOptions([
                'http_errors' => false
            ]);
        $this->assertSame(100, self::$cfg->getMaxAttemptsOnServerErrors());
        $this->assertSame(200, self::$cfg->getMaxAttemptsOnRateLimitErrors());
        $this->assertSame('2019-07', self::$cfg->getApiVersion());
        $this->assertSame(0.95, self::$cfg->getMaxLimitRate());
        $this->assertSame(3, self::$cfg->getMaxLimitRateSleepSeconds());
        $this->assertInstanceOf(TestResponseDataFormatter::class, self::$cfg->getResponseFormatter());
        $this->assertArraySubset(['http_errors' => false], self::$cfg->getHttpClientOptions());
    }

    public function testConstructorThrowErrorsWhenInvalidConfigurationOptionPassed()
    {
        $this->expectException(InvalidArgumentException::class);
        new ClientConfig([
            'foo' => 'bar'
        ]);
    }

    public function testSetInvalidApiVersionFormatThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/invalid API version format/i');
        self::$cfg->setApiVersion('201910');
    }

    public function testSetInvalidApiVersionYearThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/invalid API version year/i');
        self::$cfg->setApiVersion('2018-10');
    }

    public function testSetInvalidApiVersionMonthThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/invalid API version month/i');
        self::$cfg->setApiVersion('2019-05');
    }

    /**
     * @dataProvider arrayAccessPropertiesDataProvider
     * @param $property
     * @param $values
     */
    public function testArrayAccess($property, $values)
    {
        $config = new ClientConfig();
        $this->assertArrayHasKey($property, $config);

        $config->{'set' . ucfirst($property)}($values[0]);
        $this->assertSame($values[0], $config[$property]);

        $config[$property] = $values[1];
        $this->assertSame($values[1], $config[$property]);

        $this->assertTrue(!empty($config[$property]));
        $this->assertTrue(isset($config[$property]));

        unset($config[$property]);
        $this->assertEmpty($config[$property]);
    }

    public function arrayAccessPropertiesDataProvider()
    {
        return [
            ['handle', ['test', 'test2']],
            ['accessToken', ['test-access-token', 'test-access-token-2']]
        ];
    }

    public function testChangingHandleUsingArrayAccessAlsoChangesBaseUriAndPermanentDomain()
    {
        $config = new ClientConfig();
        $config['handle'] = 'xyz';
        $domain = 'xyz.myshopify.com';
        $this->assertSame($domain, $config['permanentDomain']);
        $this->assertSame($domain, $config->getPermanentDomain());
        $this->assertContains($domain, $config['baseUrl']);
        $this->assertContains($domain, $config->getPermanentDomain());

        $config['handle'] = 'abc';
        $domain = 'abc.myshopify.com';
        $this->assertSame($domain, $config['permanentDomain']);
        $this->assertSame($domain, $config->getPermanentDomain());
        $this->assertContains($domain, $config['baseUrl']);
        $this->assertContains($domain, $config->getPermanentDomain());
    }
}
