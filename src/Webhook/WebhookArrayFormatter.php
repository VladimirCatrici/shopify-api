<?php

namespace VladimirCatrici\Shopify\Webhook;

class WebhookArrayFormatter implements WebhookDataFormatterInterface
{
    /**
     * @param $data
     * @return array
     */
    public function output($data)
    {
        return json_decode($data, true);
    }
}
