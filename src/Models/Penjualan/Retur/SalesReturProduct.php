<?php

namespace Icso\Accounting\Models\Penjualan\Retur;

use App\Models\Tenant\Master\Product;
use App\Models\Tenant\Master\Tax;
use App\Models\Tenant\Master\Unit;
use App\Models\Tenant\Penjualan\Order\SalesOrderProduct;
use App\Models\Tenant\Penjualan\Pengiriman\SalesDeliveryProduct;
use Illuminate\Database\Eloquent\Model;

class SalesReturProduct extends Model
{
    protected $table = 'als_sales_retur_product';
    protected $guarded = [];
    public $timestamps = false;

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function deliveryproduct(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesDeliveryProduct::class,'delivery_product_id');
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
        return $this->belongsTo(SalesOrderProduct::class,'order_product_id');
    }

}
