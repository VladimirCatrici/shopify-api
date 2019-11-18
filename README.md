Shopify API
=
This is a simple PHP library that provides a quick and easy way to work with Shopify REST API.
It uses Guzzle as HTTP client. 

Installation
-

```shell
composer require vladimircatrici/shopify
```

Usage
-

### Initialization
```php
require_once 'vendor/autoload.php';
use VladimirCatrici\Shopify;
$clientConfig = new ClientConfig([
    'handle' => 'your-shop-handle', // with or without ".myshopify.com"
    'accessToken' => 'your-access-token'
]);
Shopify\ClientManager::setConfig('default', $clientConfig);
$api = Shopify\ClientManager::get('default');
```

### Configuration

There are a few additional options you can pass to the ClientManager.

-   `apiVersion` (default: `the oldest stable supported version`)
The Shopify API version you want to use.  
Read more about [API versioning at Shopify](https://help.shopify.com/en/api/versioning).   

-   `maxAttemptsOnServerErrors` (default: `1`)  
Number of attempts trying to execute the request. 
It's useful because sometimes Shopify may respond with 500 error.
I would recommend set this to `2` or `3`. The default value is `1` though. 

-   `maxAttemptsOnRateLimitErrors` (default: `1`)  
Number of attempts trying to execute the request on getting `429 Too Many Connections` error.
This might be useful if the same API key is used by other apps which may lead to exceeding the rate limit.
The recommended value would be somewhere under the 10.  

-   `maxLimitRate` (default: `0.5`)  
Number between 0 and 1 describing the maximum limit rate the client should reach before going sleep. 
See `max_limit_rate_sleep_sec` option.  

-   `maxLimitRateSleepSeconds` (default: `1`)  
Number of seconds to sleep when API reaches the maximum API limit rate specified in `max_limit_rate` option.

-   `responseFormatter` (default: instance of `ResponseDefaultFormatter`)  
You can create and set up another data formatter for response you're getting from Shopify.
Your formatter should implement simple `ResponseDataFormatterInterface`. 

### Basic usage

The client implements all 4 HTTP methods that Shopify REST API supports. Method names are:
-   get(_string_ $endpoint, _array_ $options)
-   post(_string_ $endpoint, _array_ $data)
-   put(_string_ $endpoint, _array_ $data)
-   delete(_string_ $endpoint)

The `$endpoint` parameter must always be a string that represents a Shopify API endpoint. 
It should not contain the `/admin/api/#{api_version}/` part in the beginning. 
The `.json` is not necessary in the end of the path as well. For example if Shopify documentations 
shows the endpoint path as `GET /admin/api/#{api_version}/orders.json`, you can just use:
```php
$api->get('orders');
```
 
See more examples below:

#### Get items

```php
$numProducts = $api->get('products/count'); // int
$products = $api->get('products'); // array
foreach ($products as $product) {
  echo sprintf('#%d. %s<br>', 
    $product['id'], $product['title']);
}
```

#### Get `id` and `title` fields of 250 items from the 2nd page

```php
$products = $api->get('products', [
  'fields' => 'id,title',
  'limit' => 250,
  'page' => 2
]);
```

#### Get single item
```php
$product = $api->get('products/123456789');
echo sprintf('#%d. %s', $product['id'], $product['title']);
```

#### Update item
```php
$product = $api->put('products/123456789', [
    'title' => 'New title'
]);
echo sprintf('#%d. %s', $product['id'], $product['title']); // #1. New title
```

#### Create item
```php
$product = $api->post('products', [
    'title' => 'New product' 
]);
```

#### Delete item
```php
$api->delete('products/123456789');
if ($api->respCode == 200) {
    // Item has been successfully removed
}
```

Collection
-
You can use Collection object to get all the items from the specific endpoint. 
This library works fine with both page-based and cursor-based pagination and switches between them based on API version.
```php
use VladimirCatrici\Shopify\ClientManager;
$api = ClientManager::get('default');
$products = new Collection($api, 'products');
foreach ($products as $product) {
    printf('#%d. %s [$%f], 
        $product['id'], $product['title'], $product['price']
    );
}
```

Webhooks
-
You can use this library to listen for shop's webhooks.
```php
use VladimirCatrici\Shopify;
if (Shopify\Webhook::validate('your-webhook-token')) {
    printf('`%s` webhook triggered on your store (%s). Data received: %s', 
        Shopify\Webhook::getTopic(), 
        Shopify\Webhook::getShopDomain(),
        Shopify\Webhook::getData()
    );
} else {
    // Invalid request | Unauthorized webhook | Data corrupted
}

// You can also get webhook data as array right away
$data = Shopify\Webhook::getDataAsArray();
printf('Product ID#%d, product title: %s', 
    $data['id'], $data['title']
);
```

Troubleshooting
-
```php
use VladimirCatrici\Shopify\Exception\RequestException;
try {
    $products = $api->get('products');
} catch (RequestException $e) {
    $request = $e->getRequest(); // PSR-7/Request object

    $response = $e->getResponse(); // PSR-7/Response object
    $code = $response->getStatusCode(); // int
    $headers = $response->getHeaders(); // array
    $bodyStream = $response->getBody(); // Stream (PSR-7/StreamInterface)
    $bodyContent = (string) $response->getBody(); // string (or $body->__toString())
    
    // Details of the errors including exception message, request and response details
    echo $e->getDetailsJson();
}
```
