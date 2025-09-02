<?php
namespace Icso\Accounting\Models\Master;


use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Icso\Accounting\Models\Persediaan\Adjustment;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Models\Persediaan\StockAwal;
use Icso\Accounting\Models\Persediaan\StockUsage;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $table = 'als_warehouse';
    protected $guarded = [];
    public $timestamps = false;

    public static $rules = [
        'warehouse_name' => 'required'
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($warehouse) {
            if ($warehouse->canDelete()) {
                // Deletion is allowed
            } else {
                // Deletion is not allowed
                return false; // This prevents the record from being deleted
            }
        });
    }

    public function canDelete()
    {
        // Check if there are associated comments
        if ($this->stockawal()->count() > 0 || $this->adjustment()->count() > 0 || $this->inventory()->count() > 0 || $this->purchaseinvoice()->count() > 0 || $this->purchasereceive()->count() > 0 || $this->salesdelivery()->count() > 0 || $this->salesinvoice()->count() > 0 || $this->stockusage()->count() > 0) {
            return false; // Deletion not allowed
        }

        return true; // Deletion allowed
    }

    public function stockawal()
    {
        return $this->hasMany(StockAwal::class, 'warehouse_id');
    }

    public function adjustment(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Adjustment::class,'warehouse_id');
    }

    public function inventory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Inventory::class, 'warehouse_id');
    }

    public function purchaseinvoice(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseInvoicing::class, 'warehouse_id');
    }

    public function purchasereceive(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseReceived::class, 'warehouse_id');
    }

    public function salesdelivery(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesDelivery::class, 'warehouse_id');
    }

    public function salesinvoice(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesInvoicing::class, 'warehouse_id');
    }

    public function stockusage(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockUsage::class, 'warehouse_id');
    }
}
