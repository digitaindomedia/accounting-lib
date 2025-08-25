<?php

namespace Icso\Accounting\Models\Penjualan\Invoicing;

use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Models\Penjualan\Order\SalesOrder;
use Icso\Accounting\Models\Penjualan\Order\SalesOrderProduct;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Database\Eloquent\Model;

class SalesInvoicing extends Model
{
    protected $table = 'als_sales_invoice';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'invoice_date' => 'required'
    ];

    public function getCreatedByNameAttribute()
    {
        return Helpers::getNamaUser($this->created_by);
    }

   /* public function getCreatedByAttribute($value)
    {
        return Helpers::getNamaUser($value);
    } */

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function invoicedelivery()
    {
        return $this->hasMany(SalesInvoicingDelivery::class,'invoice_id');
    }

    public function orderproduct()
    {
        return $this->hasMany(SalesOrderProduct::class,'invoice_id');
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

}
