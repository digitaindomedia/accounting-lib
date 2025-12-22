<?php
namespace Icso\Accounting\Models\Persediaan;

use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Traits\CreatedByName;
use Illuminate\Database\Eloquent\Model;

class Adjustment extends Model
{
    use CreatedByName;
    protected $table = 'als_adjustment';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name','attachments'];

    public static $rules = [
        'adjustment_date' => 'required',
        'warehouse_id' => 'required',
        'coa_adjustment_id' => 'required',
        'adjustment_type' => 'required',
        'adjustmentproduct' => 'required'
    ];

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class,'warehouse_id');
    }

    public function coa_adjustment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class,'coa_adjustment_id');
    }

    public function adjustmentproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AdjustmentProducts::class,'adjustment_id');
    }

    public function getAttachmentsAttribute()
    {
        $baseUrl = url('storage/'.tenant()->id.'/app/public/');
        $res = AdjustmentMeta::where('adjustment_id', $this->id)->where('meta_key','upload')->get();
        // Modify each meta_value to include the base URL
        $res->each(function ($item) use ($baseUrl) {
            $item->meta_value = $baseUrl . '/' . $item->meta_value;
        });

        return $res;
    }
}
