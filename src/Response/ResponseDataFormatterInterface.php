<?php


namespace VladimirCatrici\Shopify\Response;


use VladimirCatrici\Shopify\FormatterInterface;

interface ResponseDataFormatterInterface extends FormatterInterface {
    /**
     * @param string $data Response body, JSON formatted string expected
     * @return mixed
     */
    public function output(string $data);
}
