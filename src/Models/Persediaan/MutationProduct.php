<?php

namespace Icso\Accounting\Models\Persediaan;

use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Unit;
use Illuminate\Database\Eloquent\Model;

class MutationProduct extends Model
{
    protected $table = 'als_warehouse_mutation_product';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['qty_out','qty_accept','qty_tercatat'];

    public function getQtyOutAttribute()
    {
        $stok = 0;

        return $stok;
    }

    public function getQtyAcceptAttribute()
    {
        $stok = 0;

        return $stok;
    }

    public function getQtyTercatatAttribute()
    {
        $stok = 0;

        return $stok;
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class,'product_id');
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
