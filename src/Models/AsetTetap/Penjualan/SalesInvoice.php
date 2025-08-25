<?php

namespace Icso\Accounting\Models\AsetTetap\Penjualan;


use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseOrder;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Repositories\AsetTetap\Penjualan\SalesInvoiceRepo;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Traits\CreatedByName;
use Illuminate\Database\Eloquent\Model;

class SalesInvoice extends Model
{
    use CreatedByName;
    protected $table = 'als_aset_tetap_sales';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['coa_piutang_lain','total_payment','created_by_name'];

    public static $rules = [
        'sales_date' => 'required',
        'price' => 'required'
    ];

    public function getCoaPiutangLainAttribute()
    {
        $akunPiutangLainLain = SettingRepo::getOptionValue(SettingEnum::COA_PIUTANG_LAIN_LAIN);
        return Coa::where(array('id' => $akunPiutangLainLain ))->first();
    }

    public function getTotalPaymentAttribute()
    {
        return SalesInvoiceRepo::getTotalPayment($this->id);
    }

    public function profitlosscoa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class, 'profit_loss_coa_id');
    }

    public function asettetap(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'aset_tetap_id');
    }
}
