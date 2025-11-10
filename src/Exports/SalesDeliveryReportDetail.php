<?php

namespace Icso\Accounting\Exports;

class SalesDeliveryReportDetail extends BaseReportExport
{
    public function __construct($data, $params)
    {
        // Pass the view name to the base constructor
        parent::__construct($data, $params, 'accounting::sales.sales_delivery_report_detail');
    }
}