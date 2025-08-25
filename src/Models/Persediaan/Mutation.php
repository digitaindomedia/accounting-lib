<?php

namespace Icso\Accounting\Models\Persediaan;


use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Traits\CreatedByName;
use Illuminate\Database\Eloquent\Model;

class Mutation extends Model
{
    use CreatedByName;

    protected $table = 'als_warehouse_mutation';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'mutation_date' => 'required',
        'from_warehouse_id' => 'required',
        'to_warehouse_id' => 'required'
    ];

    public function mutationproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MutationProduct::class, 'mutation_id');
    }

    public function mutationmeta(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MutationMeta::class, 'mutation_id');
    }

    public function fromwarehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class,'from_warehouse_id');
    }

    public function towarehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class,'to_warehouse_id');
    }
}
