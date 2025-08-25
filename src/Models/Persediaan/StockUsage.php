<?php

namespace Icso\Accounting\Models\Persediaan;

use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Database\Eloquent\Model;

class StockUsage extends Model
{
    protected $table = 'als_stock_usage';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'usage_date' => 'required',
        'warehouse_id' => 'required',
        'stockusageproduct' => 'required'
    ];

    public function getCreatedByNameAttribute()
    {
        return Helpers::getNamaUser($this->created_by);
    }

    /*public function getCreatedByAttribute($value)
    {
        return Helpers::getNamaUser($value);
    }*/

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class,'warehouse_id');
    }

    public function coa_stock(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class,'coa_id');
    }

    public function stockusageproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockUsageProduct::class,'usage_stock_id');
    }
}
