<?php

namespace Icso\Accounting\Models\Persediaan;

use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Unit;
use Illuminate\Database\Eloquent\Model;

class StockUsageProduct extends Model
{
    protected $table = 'als_stock_usage_product';
    protected $guarded = [];
    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Product::class,'product_id');
    }

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
