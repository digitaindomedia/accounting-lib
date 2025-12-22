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

    protected $appends = ['coa_piutang_lain','total_payment','created_by_name','attachments'];

    public static $rules = [
        'sales_date' => 'required',
        'price' => 'required|gt:0',
        'aset_tetap_id' => 'required',
        'profit_loss_coa_id' => 'required',
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

    public function getAttachmentsAttribute()
    {
        $baseUrl = url('storage/'.tenant()->id.'/app/public/');
        $res = SalesInvoiceMeta::where('sales_id', $this->id)->where('meta_key','upload')->get();
        // Modify each meta_value to include the base URL
        $res->each(function ($item) use ($baseUrl) {
            $item->meta_value = $baseUrl . '/' . $item->meta_value;
        });

        return $res;
    }
}
