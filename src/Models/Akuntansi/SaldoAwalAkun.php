<?php

namespace Icso\Accounting\Models\Akuntansi;

use Icso\Accounting\Models\Master\Coa;
use Illuminate\Database\Eloquent\Model;

class SaldoAwalAkun extends Model
{
    protected $table = 'als_saldo_awal_akun';
    protected $guarded = [];
    public $timestamps = false;

    public function coa(){
        return $this->belongsTo(Coa::class, 'coa_id');
    }
}
