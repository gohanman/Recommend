<?php

namespace COREPOS\Recommend\Driver;

/**
 * @interface Driver
 *
 * A Driver implementation is responsible for the
 * Extract step in ETL. It needs to be able to
 * get customer purchases for a given date range
 * and a name value for a given item.
 */
interface Driver
{
    public function getTransactions($startDate, $endDate);
    public function getItem($upc);
}

