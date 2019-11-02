<?php


namespace VladimirCatrici\Shopify;


interface FormatterInterface {
    /**
     * @param mixed $input
     * @return mixed
     */
    public function output(string $input);
}
