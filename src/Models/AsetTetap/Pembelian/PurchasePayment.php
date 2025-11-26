<?php

namespace Icso\Accounting\Models\AsetTetap\Pembelian;

use Icso\Accounting\Models\AsetTetap\Penjualan\SalesInvoice;
use Icso\Accounting\Models\Master\PaymentMethod;
use Icso\Accounting\Traits\CreatedByName;
use Illuminate\Database\Eloquent\Model;

class PurchasePayment extends Model
{
    use CreatedByName;
    protected $table = 'als_aset_tetap_payment';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'payment_date' => 'required',
        'payment_method_id' => 'required',
        'invoice_id' => 'required',
        'total' => 'required|gt:0'
    ];

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'invoice_id');
    }

    public function sales_invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }

    public function payment_method(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
