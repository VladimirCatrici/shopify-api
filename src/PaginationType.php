<?php

namespace VladimirCatrici\Shopify;

abstract class PaginationType {
    const NOT_REQUIRED = 0;
    const CURSOR = 1;
    const SINCE = 2;
    const PAGE = 3;
}
