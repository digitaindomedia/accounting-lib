<?php

namespace Icso\Accounting\Models\Pembelian\Penerimaan;

use Illuminate\Database\Eloquent\Model;

class PurchaseReceivedProductItem extends Model
{
    protected $table = 'als_purchase_receive_product_items';
    protected $guarded = [];
    public $timestamps = false;
}