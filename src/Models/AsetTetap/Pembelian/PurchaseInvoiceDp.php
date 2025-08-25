<?php

namespace Icso\Accounting\Models\AsetTetap\Pembelian;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceDp extends Model
{
    protected $table = 'als_aset_tetap_invoice_dp';
    protected $guarded = [];
    public $timestamps = false;

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'invoice_id');
    }

    public function downpayment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseDownPayment::class, 'dp_id');
    }
}
