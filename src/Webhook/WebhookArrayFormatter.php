<?php


namespace VladimirCatrici\Shopify\Webhook;


class WebhookArrayFormatter implements WebhookDataFormatterInterface {
    /**
     * @param $data
     * @return array
     */
    public function output(string $data) : array {
        return json_decode($data, true);
    }
}
