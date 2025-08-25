<?php

namespace Icso\Accounting\Models\Pembelian\Pembayaran;

use Icso\Accounting\Models\Master\PaymentMethod;
use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Traits\CreatedByName;
use Illuminate\Database\Eloquent\Model;

class PurchasePayment extends Model
{
    use CreatedByName;
    protected $table = 'als_purchase_payment';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'payment_date' => 'required',
        'payment_method_id' => 'required',
        'invoice' => 'required'
    ];

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class,'vendor_id');
    }

    public function payment_method(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchasePaymentInvoice::class, 'payment_id')->where('invoice_id', '!=', 0);
    }

    public function invoiceretur(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchasePaymentInvoice::class, 'payment_id')->where('retur_id', '!=', 0);
    }
}
