<?php

namespace Icso\Accounting\Models\AsetTetap\Pembelian;

use Illuminate\Database\Eloquent\Model;

class Depression extends Model
{
    protected $table = 'als_aset_tetap_depression';
    protected $guarded = [];
    public $timestamps = false;


    public function receive(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseReceive::class,'receive_id');
    }

}
