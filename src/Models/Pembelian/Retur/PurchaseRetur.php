<?php

namespace Icso\Accounting\Models\Pembelian\Retur;


use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Icso\Accounting\Traits\CreatedByName;
use Illuminate\Database\Eloquent\Model;

class PurchaseRetur extends Model
{
    use CreatedByName;
    protected $table = 'als_purchase_retur';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name'];

    public static $rules = [
        'retur_date' => 'required',
        'returproduct' => 'required'
    ];

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class,'vendor_id');
    }

    public function receive(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseReceived::class, 'receive_id');
    }

    public function returproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseReturProduct::class,'retur_id');
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseInvoicing::class, 'invoice_id');
    }
}
