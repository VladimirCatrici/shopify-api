<?php

namespace VladimirCatrici\Shopify;

interface ClientInterface
{
    public function get($endpoint, $query = []);
    public function post($endpoint, $data = []);
    public function put($endpoint, $data = []);
    public function delete($endpoint);
}
