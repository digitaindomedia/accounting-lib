<?php

namespace Icso\Accounting\Exports;

class SalesDownPaymentReportExport extends BaseReportExport
{
    public function __construct($data, $params)
    {
        // Pass the view name to the base constructor
        parent::__construct($data, $params, 'sales.sales_downpayment_report');
    }
}
