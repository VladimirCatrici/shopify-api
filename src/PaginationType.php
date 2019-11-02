<?php

namespace VladimirCatrici\Shopify;

abstract class PaginationType {
    public const NOT_REQUIRED = 0;
    public const CURSOR = 1;
    public const SINCE = 2;
    public const PAGE = 3;
}
