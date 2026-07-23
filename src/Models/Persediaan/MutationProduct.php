<?php

namespace Icso\Accounting\Models\Persediaan;

use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Unit;
use Icso\Accounting\Utils\VarType;
use Illuminate\Database\Eloquent\Model;

class MutationProduct extends Model
{
    protected $table = 'als_warehouse_mutation_product';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['qty_out','qty_accept','qty_tercatat'];

    public function getQtyOutAttribute()
    {
        return (float) ($this->qty ?? 0);
    }

    public function getQtyAcceptAttribute()
    {
        $mutation = Mutation::find($this->mutation_id);
        if (!$mutation || $mutation->mutation_type !== VarType::MUTATION_TYPE_OUT) {
            return 0;
        }

        $mutationInIds = Mutation::where('mutation_out_id', $this->mutation_id)
            ->where('mutation_type', VarType::MUTATION_TYPE_IN)
            ->pluck('id');

        if ($mutationInIds->isEmpty()) {
            return 0;
        }

        return (float) self::whereIn('mutation_id', $mutationInIds)
            ->where('product_id', $this->product_id)
            ->where('unit_id', $this->unit_id)
            ->sum('qty');
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

    public function mutation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Mutation::class,'mutation_id');
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
