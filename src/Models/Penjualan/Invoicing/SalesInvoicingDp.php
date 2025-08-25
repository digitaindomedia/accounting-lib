<?php

namespace Icso\Accounting\Accountingp\Models\Penjualan\Invoicing;


use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Models\Penjualan\UangMuka\SalesDownpayment;
use Illuminate\Database\Eloquent\Model;

class SalesInvoicingDp extends Model
{
    protected $table = 'als_sales_invoice_dp';
    protected $guarded = [];
    public $timestamps = false;

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesInvoicing::class, 'invoice_id');
    }

    public function downpayment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesDownpayment::class, 'dp_id');
    }
}
