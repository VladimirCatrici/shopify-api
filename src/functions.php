<?php

namespace VladimirCatrici\Shopify;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * @param mixed $date DateTime string (YYYY-MM[...]) or DateTime object
 * @return string
 * @throws Exception
 */
function getOldestSupportedVersion($date = '')
{
    $datetime = $date instanceof DateTime ? $date : new DateTime($date);
    $datetime->setTimezone(new DateTimeZone('UTC'));
    $currentYearMonth = $datetime->format('Y-m');
    if ($currentYearMonth < '2020-04') {
        return '2019-04';
    }
    $currentYearMonthParts = explode('-', $currentYearMonth);
    $currentYear = $currentYearMonthParts[0];
    $currentMonth = $currentYearMonthParts[1];

    $monthMapping = [
        '01' => '04',
        '02' => '04',
        '03' => '04',
        '04' => '07',
        '05' => '07',
        '06' => '07',
        '07' => '10',
        '08' => '10',
        '09' => '10',
        '10' => '01',
        '11' => '01',
        '12' => '01'
    ];
    $returnMonth = $monthMapping[$currentMonth];

    // If the latest supported version has been released in April or later,
    // then current date is Jan-Sep and that means that it was released in the previous year.
    // Still the same year for any date withing Oct-Dec range.
    return $currentYear - ($returnMonth >= '04' ? 1 : 0) . '-' . $returnMonth;
}
