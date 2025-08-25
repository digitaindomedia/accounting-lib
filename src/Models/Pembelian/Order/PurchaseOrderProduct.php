<?php

namespace Icso\Accounting\Models\Pembelian\Order;


use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Tax;
use Icso\Accounting\Models\Master\Unit;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderProduct extends Model
{
    protected $table = 'als_purchase_order_product';
    protected $guarded = [];
    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit(){
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function tax(){
        return $this->belongsTo(Tax::class, 'tax_id');
    }
}
