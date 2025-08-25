<?php

namespace Icso\Accounting\Models\AsetTetap\Pembelian;


use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Repositories\AsetTetap\Pembelian\DepressionRepo;
use Icso\Accounting\Traits\CreatedByName;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use CreatedByName;
    protected $table = 'als_aset_tetap';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['total_akumulasi_penyusutan', 'created_by_name'];

    public static $rules = [
        'nama_aset' => 'required',
        'aset_tetap_date' => 'required',
        'harga_beli' => 'required|gt:0',
        'qty' => 'required|gt:0'
    ];


    public function getTotalAkumulasiPenyusutanAttribute()
    {
        // Here you can define the logic to calculate the credit
        // For example, summing up credits from related transactions
        $total = DepressionRepo::totalDepresiasiByAsetId($this->id);
        return $total;
    }

    public function aset_tetap_coa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class, 'aset_tetap_coa_id');
    }

    public function dari_akun_coa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class, 'dari_akun_coa_id');
    }

    public function akumulasi_penyusutan_coa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class,'akumulasi_penyusutan_coa_id');
    }

    public function penyusutan_coa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class,'penyusutan_coa_id');
    }

    public function downpayment(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseDownPayment::class, 'order_id');
    }
}
