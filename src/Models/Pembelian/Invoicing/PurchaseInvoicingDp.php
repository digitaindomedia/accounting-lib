<?php

namespace Icso\Accounting\Models\Pembelian\Invoicing;


use Icso\Accounting\Models\Pembelian\UangMuka\PurchaseDownPayment;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoicingDp extends Model
{
    protected $table = 'als_purchase_invoice_dp';
    protected $guarded = [];
    public $timestamps = false;

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseInvoicing::class, 'invoice_id');
    }

    public function downpayment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseDownPayment::class, 'dp_id');
    }
}
