<?php

namespace Icso\Accounting\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SalesOrderReportDetail extends BaseReportExport
{
    public function __construct($data, $params)
    {
        // Pass the view name to the base constructor
        parent::__construct($data, $params, 'accounting::sales.sales_order_report_detail');
    }
}
