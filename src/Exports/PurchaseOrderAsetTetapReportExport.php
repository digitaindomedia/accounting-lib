<?php

namespace Icso\Accounting\Exports;

class PurchaseOrderAsetTetapReportExport extends BaseReportExport
{
    public function __construct($data, $params)
    {
        // Pass the view name to the base constructor
        parent::__construct($data, $params, 'accounting::fixasset.purchase_order_report');
    }
}