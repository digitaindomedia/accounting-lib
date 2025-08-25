<?php

namespace Icso\Accounting\Models\Penjualan\Spk;

use App\Models\Tenant\Master\Vendor;
use App\Models\Tenant\Penjualan\Order\SalesOrder;
use App\Utils\Helpers;
use Illuminate\Database\Eloquent\Model;

class SalesSpk extends Model
{
    protected $table = 'als_sales_spk';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'spk_date' => 'required',
        'order_id' => 'required',
        'vendor_id' => 'required',
        'spkproduct' => 'required'
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
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesOrder::class,'order_id');
    }

    public function spkproduct()
    {
        return $this->hasMany(SalesSpkProduct::class, 'spk_id');
    }

}
