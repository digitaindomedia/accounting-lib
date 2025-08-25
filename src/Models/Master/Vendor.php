<?php
namespace Icso\Accounting\Models\Master;

use App\Models\Tenant\Pembelian\Bast\PurchaseBast;
use App\Models\Tenant\Pembelian\Invoicing\PurchaseInvoicing;
use App\Models\Tenant\Pembelian\Order\PurchaseOrder;
use App\Models\Tenant\Pembelian\Pembayaran\PurchasePayment;
use App\Models\Tenant\Pembelian\Pembayaran\PurchasePaymentInvoice;
use App\Models\Tenant\Pembelian\Penerimaan\PurchaseReceived;
use App\Models\Tenant\Pembelian\Retur\PurchaseRetur;
use App\Models\Tenant\Penjualan\Invoicing\SalesInvoicing;
use App\Models\Tenant\Penjualan\Pembayaran\SalesPaymentInvoice;
use App\Models\Tenant\Penjualan\Pengiriman\SalesDelivery;
use App\Models\Tenant\Penjualan\Retur\SalesRetur;
use App\Models\Tenant\Penjualan\Spk\SalesSpk;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $table = 'als_vendor';
    protected $guarded = [];
    public $timestamps = false;

    public static $rules = [
        'vendor_name' => 'required',
        'vendor_company_name' => 'required',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($vendor) {
            if ($vendor->canDelete()) {
                // Deletion is allowed
            } else {
                // Deletion is not allowed
                return false; // This prevents the record from being deleted
            }
        });
    }

    public function canDelete()
    {
        // Check if there are associated records
        if (
            $this->purchaseinvoice()->count() > 0 ||
            $this->purchasebast()->count() > 0 ||
            $this->purchaseorder()->count() > 0 ||
            $this->purchasereceive()->count() > 0 ||
            $this->purchasepayment()->count() > 0 ||
            $this->purchasepaymentinvoice()->count() > 0 ||
            $this->purchaseretur()->count() > 0 ||
            $this->salesspk()->count() > 0 ||
            $this->salesretur()->count() > 0 ||
            $this->salesdelivery()->count() > 0 ||
            $this->salesinvoice()->count() > 0 ||
            $this->salespaymentinvoice()->count() > 0
        ) {
            return false; // Deletion not allowed
        }

        return true; // Deletion allowed
    }

    public function purchaseinvoice(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseInvoicing::class, 'vendor_id');
    }

    public function purchasebast(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseBast::class, 'vendor_id');
    }

    public function purchaseorder(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'vendor_id');
    }

    public function purchasereceive(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseReceived::class, 'vendor_id');
    }

    public function purchasepayment(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchasePayment::class, 'vendor_id');
    }

    public function purchasepaymentinvoice(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchasePaymentInvoice::class, 'vendor_id');
    }

    public function purchaseretur(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseRetur::class, 'vendor_id');
    }

    public function salesdelivery(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesDelivery::class, 'vendor_id');
    }

    public function salesinvoice(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesInvoicing::class, 'vendor_id');
    }

    public function salespaymentinvoice(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesPaymentInvoice::class, 'vendor_id');
    }

    public function salesretur(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesRetur::class, 'vendor_id');
    }

    public function salesspk(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesSpk::class, 'vendor_id');
    }

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }


}
