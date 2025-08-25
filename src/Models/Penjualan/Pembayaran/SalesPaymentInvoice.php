<?php

namespace Icso\Accounting\Models\Penjualan\Pembayaran;

use App\Models\Tenant\Akuntansi\Jurnal;
use App\Models\Tenant\Master\Coa;
use App\Models\Tenant\Penjualan\Invoicing\SalesInvoicing;
use App\Models\Tenant\Penjualan\Retur\SalesRetur;
use Illuminate\Database\Eloquent\Model;

class SalesPaymentInvoice extends Model
{
    protected $table = 'als_sales_payment_invoice';
    protected $guarded = [];
    public $timestamps = false;

    public function coadiscount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class,'coa_id_discount');
    }

    public function coaoverpayment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class,'coa_id_overpayment');
    }

    public function salesinvoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesInvoicing::class,'invoice_id');
    }

    public function salespayment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesPayment::class,'payment_id');
    }

    public function jurnal(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Jurnal::class,'jurnal_id');
    }

    public function retur() {
        return $this->belongsTo(SalesRetur::class, 'retur_id');
    }
}
