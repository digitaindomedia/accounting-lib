<?php

namespace Icso\Accounting\Models\Akuntansi;

use Icso\Accounting\Models\Master\Coa;
use Illuminate\Database\Eloquent\Model;

class JurnalTransaksi extends Model
{
    protected $table = 'als_jurnal_transactions';
    protected $guarded = [];
    public $timestamps = false;

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }
}
