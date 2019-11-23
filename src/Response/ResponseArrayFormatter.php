<?php

namespace VladimirCatrici\Shopify\Response;

class ResponseArrayFormatter implements ResponseDataFormatterInterface
{
    /**
     * @param string $data Response body, JSON formatted string expected
     * @return array
     */
    public function output($data)
    {
        $body = json_decode($data, true, 512, JSON_BIGINT_AS_STRING);
        return is_array($body) && !empty(key($body)) ? $body[key($body)] : $body;
    }
}
