<?php


namespace VladimirCatrici\Shopify\Webhook;


use VladimirCatrici\Shopify\FormatterInterface;

interface WebhookDataFormatterInterface extends FormatterInterface {
    /**
     * @param string $data Webhook data, JSON formatted
     * @return mixed
     */
    public function output(string $data);
}
