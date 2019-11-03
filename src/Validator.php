<?php


namespace VladimirCatrici\Shopify;


use InvalidArgumentException;

class Validator
{
    public static function validateApiVersion($val) {
        $readMoreAboutApiVersioning = 'Read more about versioning here: https://help.shopify.com/en/api/versioning';
        if (!preg_match('/^(\d{4})-(\d{2})$/', $val, $matches)) {
            throw new InvalidArgumentException(
                sprintf('Invalid API version format: "%s". The "YYYY-MM" format expected. ' . $readMoreAboutApiVersioning,
                    $val)
            );
        }
        if ($matches[1] < 2019) {
            throw new InvalidArgumentException(
                sprintf('Invalid API version year: "%s". The API versioning has been released in 2019. ' . $readMoreAboutApiVersioning,
                    $val)
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
}