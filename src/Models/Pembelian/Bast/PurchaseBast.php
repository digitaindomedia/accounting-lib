<?php

namespace Icso\Accounting\Models\Pembelian\Bast;

use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrder;
use Icso\Accounting\Traits\CreatedByName;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Database\Eloquent\Model;

class PurchaseBast extends Model
{
    use CreatedByName;
    protected $table = 'als_purchase_bast';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'bast_date' => 'required',
        'order_id' => 'required',
        'vendor_id' => 'required',
        'bastproduct' => 'required'
    ];

    public function getCreatedByAttribute($value)
    {
        return Helpers::getNamaUser($value);
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class,'order_id');
    }

    public function bastproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseBastProduct::class, 'bast_id');
    }
}
