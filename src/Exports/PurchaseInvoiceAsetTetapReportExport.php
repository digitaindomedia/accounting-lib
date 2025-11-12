<?php

namespace Icso\Accounting\Exports;

class PurchaseInvoiceAsetTetapReportExport extends BaseReportExport
{
    public function __construct($data, $params)
    {
        // Pass the view name to the base constructor
        parent::__construct($data, $params, 'accounting::fixasset.purchase_invoice_report');
    }
}