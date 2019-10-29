<?php


namespace VladimirCatrici\Shopify\Webhook;


interface WebhookDataFormatterInterface {
    /**
     * @param string $data Webhook data, JSON formatted
     * @return mixed
     */
    public function output(string $data);
}