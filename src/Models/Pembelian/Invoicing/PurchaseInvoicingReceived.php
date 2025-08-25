<?php

namespace Icso\Accounting\Models\Pembelian\Invoicing;

use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoicingReceived extends Model
{
    protected $table = 'als_purchase_invoice_receive';
    protected $guarded = [];
    public $timestamps = false;

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseInvoicing::class, 'invoice_id');
    }

    public function receive(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseReceived::class, 'receive_id');
    }
}
