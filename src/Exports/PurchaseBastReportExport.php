<?php

namespace Icso\Accounting\Exports;

class PurchaseBastReportExport extends BaseReportExport
{
    public function __construct($data, $params)
    {
        // Pass the view name to the base constructor
        parent::__construct($data, $params, 'accounting::purchase.purchase_bast_detail_report');
    }
}
