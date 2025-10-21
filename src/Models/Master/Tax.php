<?php
namespace Icso\Accounting\Models\Master;


use Icso\Accounting\Models\Pembelian\Bast\PurchaseBastProduct;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrderProduct;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceivedProduct;
use Icso\Accounting\Models\Pembelian\Retur\PurchaseReturProduct;
use Icso\Accounting\Models\Pembelian\UangMuka\PurchaseDownPayment;
use Icso\Accounting\Models\Penjualan\Order\SalesOrderProduct;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDeliveryProduct;
use Icso\Accounting\Models\Penjualan\Retur\SalesReturProduct;
use Icso\Accounting\Models\Penjualan\UangMuka\SalesDownpayment;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    protected $table = 'als_tax';
    protected $guarded = [];
    public $timestamps = false;

    public static $rules = [
        'tax_name' => 'required'
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($tax) {
            if ($tax->canDelete()) {
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
        if ($this->taxgroup()->count() > 0 || $this->purchasebastproduct()->count() > 0 || $this->purchasereturproduct()->count() > 0 || $this->purchasedownpayment()->count() > 0 || $this->purchaseorderproduct()->count() > 0 || $this->purchasereceiveproduct()->count() > 0 || $this->salesdeliveryproduct()->count() > 0 || $this->salesreturproduct()->count() > 0 || $this->salesdownpayment()->count() > 0 || $this->salesorderproduct()->count() > 0) {
            return false; // Deletion not allowed
        }

        return true; // Deletion allowed
    }

    public function taxgroup(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TaxGroup::class,'id_tax');
    }

    public function purchasecoa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class, 'purchase_coa_id');
    }

    public function salescoa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class, 'sales_coa_id');
    }

    public function purchasebastproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseBastProduct::class,'tax_id');
    }
    public function purchasedownpayment(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseDownPayment::class,'tax_id');
    }

    public function purchaseorderproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseOrderProduct::class,'tax_id');
    }

    public function purchasereceiveproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseReceivedProduct::class,'tax_id');
    }

    public function purchasereturproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseReturProduct::class,'tax_id');
    }

    public function salesdownpayment(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesDownpayment::class,'tax_id');
    }

    public function salesorderproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesOrderProduct::class,'tax_id');
    }

    public function salesdeliveryproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesDeliveryProduct::class,'tax_id');
    }

    public function salesreturproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesReturProduct::class,'tax_id');
    }

}
