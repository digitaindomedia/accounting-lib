<?php

namespace Icso\Accounting\Models\Akuntansi;


use Icso\Accounting\Models\Master\Coa;
use Illuminate\Database\Eloquent\Model;

class BukuPembantu extends Model
{
    protected $table = 'als_buku_pembantu';
    protected $guarded = [];
    public $timestamps = false;

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function jurnal()
    {
        return $this->belongsTo(Jurnal::class, 'jurnal_id');
    }

}
