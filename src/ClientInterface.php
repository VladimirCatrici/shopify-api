<?php

namespace VladimirCatrici\Shopify;

use VladimirCatrici\Shopify\Exception\RequestException;

/**
 * Interface ClientInterface
 * @package VladimirCatrici\Shopify
 */
interface ClientInterface
{
    /**
     * @param string $endpoint
     * @param array $query
     * @return mixed
     * @throws RequestException
     */
    public function get(string $endpoint, array $query = []);

    /**
     * @param string $endpoint
     * @param array $data
     * @return mixed
     * @throws RequestException
     */
    public function post(string $endpoint, array $data = []);

    /**
     * @param string $endpoint
     * @param array $data
     * @return mixed
     * @throws RequestException
     */
    public function put(string $endpoint, array $data = []);

    /**
     * @param string $endpoint
     * @return mixed
     * @throws RequestException
     */
    public function delete(string $endpoint);
}
