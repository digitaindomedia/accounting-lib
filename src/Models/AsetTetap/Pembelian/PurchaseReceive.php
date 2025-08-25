<?php

namespace Icso\Accounting\Models\AsetTetap\Pembelian;

use Icso\Accounting\Traits\CreatedByName;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceive extends Model
{
    use CreatedByName;
    protected $table = 'als_aset_tetap_receive';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'order_id' => 'required',
        'receive_date' => 'required'
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    public function depression(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Depression::class,'receive_id');
    }
}
