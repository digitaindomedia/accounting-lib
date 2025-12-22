<?php

namespace Icso\Accounting\Models\Penjualan\Pembayaran;

use Icso\Accounting\Models\Master\PaymentMethod;
use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Database\Eloquent\Model;

class SalesPayment extends Model
{
    protected $table = 'als_sales_payment';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name','attachments'];

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

    public function getAttachmentsAttribute()
    {
        $baseUrl = url('storage/'.tenant()->id.'/app/public/');
        $res = SalesPaymentMeta::where('payment_id', $this->id)->where('meta_key','upload')->get();
        // Modify each meta_value to include the base URL
        $res->each(function ($item) use ($baseUrl) {
            $item->meta_value = $baseUrl . '/' . $item->meta_value;
        });

        return $res;
    }
}
