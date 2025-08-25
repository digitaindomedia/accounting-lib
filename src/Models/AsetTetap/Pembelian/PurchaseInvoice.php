<?php

namespace Icso\Accounting\Models\AsetTetap\Pembelian;

use Icso\Accounting\Traits\CreatedByName;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoice extends Model
{
    use CreatedByName;
    protected $table = 'als_aset_tetap_invoice';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'invoice_date' => 'required',
        'total_tagihan' => 'required|gt:0'
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'order_id');
    }
}
