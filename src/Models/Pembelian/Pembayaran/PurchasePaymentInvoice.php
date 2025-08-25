<?php

namespace Icso\Accounting\Models\Pembelian\Pembayaran;

use Icso\Accounting\Models\Akuntansi\Jurnal;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;
use Icso\Accounting\Models\Pembelian\Retur\PurchaseRetur;
use Illuminate\Database\Eloquent\Model;

class PurchasePaymentInvoice extends Model
{
    protected $table = 'als_purchase_payment_invoice';
    protected $guarded = [];
    public $timestamps = false;

    public function coadiscount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class,'coa_id_discount');
    }

    public function purchaseinvoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseInvoicing::class,'invoice_id');
    }

    public function purchasepayment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchasePayment::class,'payment_id');
    }

    public function jurnal(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Jurnal::class,'jurnal_id');
    }

    public function coaoverpayment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class,'coa_id_overpayment');
    }

    public function retur() {
        return $this->belongsTo(PurchaseRetur::class, 'retur_id');
    }
}
