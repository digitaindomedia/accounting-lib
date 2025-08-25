<?php

namespace Icso\Accounting\Models\Penjualan\Invoicing;


use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Illuminate\Database\Eloquent\Model;

class SalesInvoicingDelivery extends Model
{
    protected $table = 'als_sales_invoice_delivery';
    protected $guarded = [];
    public $timestamps = false;

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesInvoicing::class, 'invoice_id');
    }

    public function delivery(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesDelivery::class, 'delivery_id');
    }
}
