<?php

namespace Icso\Accounting\Models\Akuntansi;

use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Utils\JurnalType;
use Illuminate\Database\Eloquent\Model;

class Jurnal extends Model
{
    protected $table = 'als_jurnal';
    protected $guarded = [];
    public $timestamps = false;
    protected $appends = ['can_delete','income','outcome','total_debet', 'total_kredit'];
    public static $rules = [
        'jurnal_date' => 'date|required',
        'jurnal_akun' => 'required',
    ];

    public function jurnal_akun()
    {
        return $this->hasMany(JurnalAkun::class, 'jurnal_id');
    }

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
        //return $this->belongsToMany(Coa::class, 'als_jurnal_akun','jurnal_id', 'coa_id' )->using(JurnalAkunPivot::class)->withPivot('id');
    }

    public function jurnal_meta(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(JurnalMeta::class, 'jurnal_id');
    }

    public function getCanDeleteAttribute()
    {
        foreach ($this->jurnal_akun as $jurnalAkun) {
            if (!$jurnalAkun->can_delete) {
                return false;
            }
        }
        return true;
    }

    public function getIncomeAttribute()
    {
        $income = 0;
        if($this->transaction_type == JurnalType::INCOME_TYPE)
        {
            $income = $this->nominal;
        }
        return $income;
    }

    public function getOutcomeAttribute()
    {
        $outcome = 0;
        if($this->transaction_type == JurnalType::EXPENSE_TYPE)
        {
            $outcome = $this->nominal;
        }
        return $outcome;
    }

    public function getTotalDebetAttribute()
    {
        return $this->jurnal_akun()->sum('debet');
    }

    public function getTotalKreditAttribute()
    {
        return $this->jurnal_akun()->sum('kredit');
    }
}
