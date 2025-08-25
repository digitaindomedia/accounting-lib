<?php

namespace Icso\Accounting\Models\Pembelian\Bast;

use Icso\Accounting\Models\Master\Tax;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrderProduct;
use Illuminate\Database\Eloquent\Model;

class PurchaseBastProduct extends Model
{
    protected $table = 'als_purchase_bast_product';
    protected $guarded = [];
    public $timestamps = false;

    public function tax(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tax::class,'tax_id');
    }

    public function orderproduct(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseOrderProduct::class, 'order_product_id');
    }
}
