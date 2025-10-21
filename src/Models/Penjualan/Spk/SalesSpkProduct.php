<?php

namespace Icso\Accounting\Models\Penjualan\Spk;

use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Penjualan\Order\SalesOrderProduct;
use Illuminate\Database\Eloquent\Model;

class SalesSpkProduct extends Model
{
    protected $table = 'als_sales_spk_product';
    protected $guarded = [];
    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function orderproduct()
    {
        return $this->belongsTo(SalesOrderProduct::class, 'order_product_id');
    }

}
