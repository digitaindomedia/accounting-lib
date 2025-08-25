<?php

namespace Icso\Accounting\Models\Penjualan\Order;

use Illuminate\Database\Eloquent\Model;

class SalesOrderMeta extends Model
{
    protected $table = 'als_sales_order_meta';
    protected $guarded = [];
    public $timestamps = false;
}
