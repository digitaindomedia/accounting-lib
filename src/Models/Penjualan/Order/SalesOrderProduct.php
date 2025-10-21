<?php

namespace Icso\Accounting\Models\Penjualan\Order;

use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Tax;
use Icso\Accounting\Models\Master\Unit;
use Illuminate\Database\Eloquent\Model;

class SalesOrderProduct extends Model
{
    protected $table = 'als_sales_order_product';
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
