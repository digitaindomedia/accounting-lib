<?php

namespace Icso\Accounting\Models\Penjualan\UangMuka;

use App\Models\Tenant\Master\Coa;
use App\Models\Tenant\Master\Tax;
use App\Models\Tenant\Penjualan\Order\SalesOrder;
use App\Models\Tenant\Penjualan\Pengiriman\SalesDeliveryMeta;
use App\Utils\Helpers;
use Illuminate\Database\Eloquent\Model;

class SalesDownpayment extends Model
{
    protected $table = 'als_sales_downpayment';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'downpayment_date' => 'required',
        'nominal' => 'required',
        'order_id' => 'required'
    ];

    public function getCreatedByNameAttribute()
    {
        return Helpers::getNamaUser($this->created_by);
    }

    /*public function getCreatedByAttribute($value)
    {
        return Helpers::getNamaUser($value);
    }*/

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function tax(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tax::class,'tax_id');
    }

    public function downpaymentmeta(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesDeliveryMeta::class, 'dp_id');
    }
}
