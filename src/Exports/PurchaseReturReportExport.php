<?php

namespace Icso\Accounting\Exports;

class PurchaseReturReportExport extends BaseReportExport
{
    public function __construct($data, $params)
    {
        // Pass the view name to the base constructor
        parent::__construct($data, $params, 'purchase.purchase_retur_report');
    }
}
