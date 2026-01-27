<?php

namespace Icso\Accounting\Models\Persediaan;


use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Traits\CreatedByName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mutation extends Model
{
    use CreatedByName;

    protected $table = 'als_warehouse_mutation';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name','attachments'];

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

    public function mutation(): BelongsTo
    {
        return $this->belongsTo(Mutation::class,'mutation_out_id');
    }

    public function getAttachmentsAttribute()
    {
        $baseUrl = url('storage/'.tenant()->id.'/app/public/');
        $res = MutationMeta::where('mutation_id', $this->id)->where('meta_key','upload')->get();
        // Modify each meta_value to include the base URL
        $res->each(function ($item) use ($baseUrl) {
            $item->meta_value = $baseUrl . '/' . $item->meta_value;
        });

        return $res;
    }
}
