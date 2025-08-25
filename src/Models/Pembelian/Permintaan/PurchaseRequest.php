<?php
namespace Icso\Accounting\Models\Pembelian\Permintaan;

use Icso\Accounting\Models\Master\Unit;
use Icso\Accounting\Traits\CreatedByName;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    use CreatedByName;
    protected $table = 'als_purchase_request';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'request_date' => 'required',
        'requestproduct' => 'required'
    ];

    public function requestproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseRequestProduct::class, 'request_id');
    }
    public function requestmeta(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseRequestMeta::class, 'request_id');
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
