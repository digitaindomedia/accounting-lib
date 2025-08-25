<?php

namespace Icso\Accounting\Models\Penjualan\Pembayaran;

use App\Models\Tenant\Master\PaymentMethod;
use App\Models\Tenant\Master\Vendor;
use App\Utils\Helpers;
use Illuminate\Database\Eloquent\Model;

class SalesPayment extends Model
{
    protected $table = 'als_sales_payment';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'payment_date' => 'required',
        'payment_method_id' => 'required',
        'invoice' => 'required'
    ];

    public function getCreatedByNameAttribute()
    {
        return Helpers::getNamaUser($this->created_by);
    }

    /*public function getCreatedByAttribute($value)
    {
        return Helpers::getNamaUser($value);
    }*/

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
        return $this->hasMany(SalesPaymentInvoice::class, 'payment_id')->where('invoice_id', '!=', 0);
    }

    public function invoiceretur(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesPaymentInvoice::class, 'payment_id')->where('retur_id', '!=', 0);
    }
}
