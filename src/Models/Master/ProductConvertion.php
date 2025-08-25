<?php

namespace Icso\Accounting\Models\Master;

use Illuminate\Database\Eloquent\Model;

class ProductConvertion extends Model
{
    protected $table = 'als_product_convertion';
    protected $guarded = [];
    public $timestamps = false;

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class,'unit_id');
    }

    public function base_unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }
}
