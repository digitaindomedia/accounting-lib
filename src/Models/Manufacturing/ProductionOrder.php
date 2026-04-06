<?php

namespace Icso\Accounting\Models\Manufacturing;

use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Unit;
use Icso\Accounting\Models\Master\Warehouse;
use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    protected $table = 'als_production_order';
    protected $guarded = [];
    public $timestamps = false;

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function bom(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Bom::class, 'bom_id');
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function outputUnit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class, 'output_unit_id');
    }

    public function coa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function materials(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductionOrderMaterial::class, 'production_order_id');
    }

    public function results(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductionOrderResult::class, 'production_order_id');
    }
}
