<?php

namespace Icso\Accounting\Models\Penjualan\Retur;

use App\Models\Tenant\Master\Vendor;
use App\Models\Tenant\Penjualan\Invoicing\SalesInvoicing;
use App\Models\Tenant\Penjualan\Pengiriman\SalesDelivery;
use App\Utils\Helpers;
use Illuminate\Database\Eloquent\Model;

class SalesRetur extends Model
{
    protected $table = 'als_sales_retur';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'retur_date' => 'required',
        'returproduct' => 'required'
    ];

    public function getCreatedByNameAttribute()
    {
        return Helpers::getNamaUser($this->created_by);
    }

   /* public function getCreatedByNameAttribute()
    {
        return Helpers::getNamaUser($this->created_by);
    }*/

    /*public function getCreatedByAttribute($value)
    {
        return Helpers::getNamaUser($value);
    }*/

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class,'vendor_id');
    }

    public function delivery(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesDelivery::class, 'delivery_id');
    }

    public function returproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesReturProduct::class,'retur_id');
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesInvoicing::class, 'invoice_id');
    }
}
