<?php

namespace Icso\Accounting\Exports;

class PurchaseInvoiceReportDetailExport extends BaseReportExport
{
    public function __construct($data, $params)
    {
        // Pass the view name to the base constructor
        parent::__construct($data, $params, 'purchase.purchase_invoice_detail_report');
    }
}
