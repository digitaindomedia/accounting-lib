<?php

namespace Icso\Accounting\Models\Pembelian\Order;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderMeta extends Model
{
    protected $table = 'als_purchase_order_meta';
    protected $guarded = [];
    public $timestamps = false;
}
