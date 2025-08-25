<?php

namespace Icso\Accounting\Models\Penjualan\Pengiriman;

use App\Models\Tenant\Master\Product;
use App\Models\Tenant\Master\Tax;
use App\Models\Tenant\Master\Unit;
use App\Models\Tenant\Penjualan\Order\SalesOrderProduct;
use Illuminate\Database\Eloquent\Model;

class SalesDeliveryProduct extends Model
{
    protected $table = 'als_sales_delivery_product';
    protected $guarded = [];
    public $timestamps = false;

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function tax(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function orderproduct()
    {
        return $this->belongsTo(SalesOrderProduct::class, 'order_product_id');
    }
}
