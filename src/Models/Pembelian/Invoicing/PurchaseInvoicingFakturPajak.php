<?php

namespace Icso\Accounting\Models\Pembelian\Invoicing;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoicingFakturPajak extends Model
{
    protected $table = 'als_purchase_invoice_faktur_pajak';
    protected $guarded = [];
    public $timestamps = false;

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseInvoicing::class, 'invoice_id');
    }
}
