<?php

namespace Icso\Accounting\Models\Pembelian\Permintaan;


use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Unit;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequestProduct extends Model
{
    protected $table = 'als_purchase_request_product';
    protected $guarded = [];
    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit(){
        return $this->belongsTo(Unit::class, 'unit_id');
    }

}
