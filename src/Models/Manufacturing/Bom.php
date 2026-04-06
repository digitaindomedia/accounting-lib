<?php

namespace Icso\Accounting\Models\Manufacturing;

use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Unit;
use Illuminate\Database\Eloquent\Model;

class Bom extends Model
{
    protected $table = 'als_bom';
    protected $guarded = [];
    public $timestamps = false;

    protected $attributes = [
        'manufacturing_mode' => 'pre_produce',
        'auto_consume_trigger' => 'invoice',
    ];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function outputUnit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class, 'output_unit_id');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BomItem::class, 'bom_id')->orderBy('sort_order');
    }

    public function productionOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'bom_id');
    }
}
