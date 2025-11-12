<?php

namespace Icso\Accounting\Exports;


class SalesReturReportExport extends BaseReportExport
{
    public function __construct($data, $params)
    {
        // Pass the view name to the base constructor
        parent::__construct($data, $params, 'accounting::sales.sales_retur_report');
    }
}