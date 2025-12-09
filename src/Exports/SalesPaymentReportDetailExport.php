<?php

namespace Icso\Accounting\Exports;

class SalesPaymentReportDetailExport extends BaseReportExport
{
    public function __construct($data, $params)
    {
        // Pass the view name to the base constructor
        parent::__construct($data, $params, 'accounting::sales.sales_payment_detail_report');
    }
}