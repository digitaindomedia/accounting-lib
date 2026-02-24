<?php

namespace Icso\Accounting\Models\Penjualan\Order;


use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Unit;
use Illuminate\Database\Eloquent\Model;

class SalesQuotationProduct extends Model
{
    protected $table = 'als_sales_quotation_product';
    protected $guarded = [];
    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
