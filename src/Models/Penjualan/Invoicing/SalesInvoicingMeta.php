<?php

namespace Icso\Accounting\Models\Penjualan\Invoicing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoicingMeta extends Model
{
    protected $table = 'als_sales_invoice_meta';
    protected $guarded = [];
    public $timestamps = false;

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoicing::class, 'invoice_id');
    }
}
