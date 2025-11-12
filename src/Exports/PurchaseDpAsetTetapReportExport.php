<?php
namespace Icso\Accounting\Exports;

class PurchaseDpAsetTetapReportExport extends BaseReportExport
{
    public function __construct($data, $params)
    {
        // Pass the view name to the base constructor
        parent::__construct($data, $params, 'accounting::fixasset.purchase_downpayment_report');
    }
}