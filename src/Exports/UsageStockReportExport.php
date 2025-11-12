<?php

namespace Icso\Accounting\Exports;


class UsageStockReportExport extends BaseReportExport
{
    public function __construct($data, $params)
    {
        // Pass the view name to the base constructor
        parent::__construct($data, $params, 'accounting::stock.usage_stock_report');
    }
}