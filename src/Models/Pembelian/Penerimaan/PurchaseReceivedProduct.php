<?php

namespace Icso\Accounting\Models\Pembelian\Penerimaan;


use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Tax;
use Icso\Accounting\Models\Master\Unit;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrderProduct;
use Icso\Accounting\Repositories\Pembelian\Received\ReceiveRepo;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceivedProduct extends Model
{
    protected $table = 'als_purchase_receive_product';
    protected $guarded = [];
    public $timestamps = false;
    protected $appends = ['qty_bs_retur'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function tax(){
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function orderproduct()
    {
        return $this->belongsTo(PurchaseOrderProduct::class, 'order_product_id');
    }

    public function getQtyBsReturAttribute()
    {
        $purchaseReceivedRepo =  new ReceiveRepo(new PurchaseReceived());
        $qtyRetur = $purchaseReceivedRepo->getQtyRetur($this->id);
        $qtyBsRetur = $this->qty - $qtyRetur;
        return $qtyBsRetur;
    }

    public function items()
    {
        return $this->hasMany(PurchaseReceivedProductItem::class, 'receive_product_id');
    }
}
