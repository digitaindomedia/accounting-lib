<?php

namespace Icso\Accounting\Models\AsetTetap\Pembelian;

use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\Tax;
use Icso\Accounting\Traits\CreatedByName;
use Illuminate\Database\Eloquent\Model;

class PurchaseDownPayment extends Model
{
    use CreatedByName;
    protected $table = 'als_aset_tetap_downpayment';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'downpayment_date' => 'required',
        'nominal' => 'required',
        'order_id' => 'required'
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }

    public function coa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function tax(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tax::class,'tax_id');
    }

    public function downpayment_meta(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseDownPaymentMeta::class, 'dp_id');
    }
}
