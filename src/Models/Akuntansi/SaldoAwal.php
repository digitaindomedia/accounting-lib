<?php

namespace Icso\Accounting\Models\Akuntansi;

use Illuminate\Database\Eloquent\Model;

class SaldoAwal extends Model
{
    protected $table = 'als_saldo_awal';
    protected $guarded = [];
    public $timestamps = false;

    public function saldoakun(){
        return $this->hasMany(SaldoAwalAkun::class, 'saldo_id');
    }
}
