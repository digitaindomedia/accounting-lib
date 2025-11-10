<?php

namespace Icso\Accounting\Exports;

class PurchaseDpReportExport extends BaseReportExport
{
    public function __construct($data, $params)
    {
        // Pass the view name to the base constructor
        parent::__construct($data, $params, 'accounting::purchase.purchase_downpayment_detail_report');
    }

}
