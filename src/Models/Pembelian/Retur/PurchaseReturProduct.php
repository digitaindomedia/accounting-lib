<?php

namespace Icso\Accounting\Models\Pembelian\Retur;

use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Tax;
use Icso\Accounting\Models\Master\Unit;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrderProduct;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceivedProduct;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturProduct extends Model
{
    protected $table = 'als_purchase_retur_product';
    protected $guarded = [];
    public $timestamps = false;

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function receiveproduct(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseReceivedProduct::class,'receive_product_id');
    }

    public function tax(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function orderproduct(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseOrderProduct::class,'order_product_id');
    }

}
