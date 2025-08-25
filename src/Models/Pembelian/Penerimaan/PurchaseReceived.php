<?php

namespace Icso\Accounting\Models\Pembelian\Penerimaan;

use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicingReceived;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrder;
use Icso\Accounting\Models\Pembelian\Retur\PurchaseRetur;
use Icso\Accounting\Traits\CreatedByName;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceived extends Model
{
    use CreatedByName;
    protected $table = 'als_purchase_receive';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'receive_date' => 'required',
        'warehouse_id' => 'required',
        'order_id' => 'required',
        'receiveproduct' => 'required'
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class,'order_id');
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function receiveproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseReceivedProduct::class,'receive_id');
    }

    public function retur(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseRetur::class,'receive_id');
    }

    public function invoicereceived(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseInvoicingReceived::class,'receive_id');
    }

}
