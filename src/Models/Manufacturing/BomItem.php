<?php

namespace Icso\Accounting\Models\Manufacturing;

use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Unit;
use Illuminate\Database\Eloquent\Model;

class BomItem extends Model
{
    protected $table = 'als_bom_item';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'is_optional' => 'boolean',
    ];

    public function bom(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Bom::class, 'bom_id');
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
