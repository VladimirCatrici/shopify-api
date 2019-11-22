<?php

namespace VladimirCatrici\Shopify\Response;

class ResponseDefaultFormatter implements ResponseDataFormatterInterface
{
    /**
     * @param string $data Response body, JSON formatted string expected
     * @return mixed Array if JSON object returned. May also return integer e.g. on "endpoint/count" request
     */
    public function output(string $data)
    {
        $body = json_decode($data, true, 512);
        return is_array($body) && !empty(key($body)) ? $body[key($body)] : $body;
    }
}
