<?php

namespace Icso\Accounting\Models\Akuntansi;

use Illuminate\Database\Eloquent\Model;

class PelunasanBukuPembantu extends Model
{
    protected $table = 'als_pelunasan_buku_pembantu';
    protected $guarded = [];
    public $timestamps = false;

    public function bukupembantu()
    {
        return $this->belongsTo(BukuPembantu::class,'buku_pembantu_id');
    }
}
