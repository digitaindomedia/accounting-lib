<?php
namespace Icso\Accounting\Models\Master;

use App\Models\Tenant\Akuntansi\BukuPembantu;
use App\Models\Tenant\Akuntansi\Jurnal;
use App\Models\Tenant\Akuntansi\JurnalTransaksi;
use App\Models\Tenant\Pembelian\Invoicing\PurchaseInvoicing;
use App\Models\Tenant\Pembelian\Order\PurchaseOrder;
use App\Models\Tenant\Pembelian\Pembayaran\PurchasePaymentInvoice;
use App\Models\Tenant\Pembelian\UangMuka\PurchaseDownPayment;
use App\Models\Tenant\Penjualan\Invoicing\SalesInvoicing;
use App\Models\Tenant\Penjualan\Pembayaran\SalesPaymentInvoice;
use App\Models\Tenant\Penjualan\UangMuka\SalesDownpayment;
use App\Models\Tenant\Persediaan\Adjustment;
use App\Models\Tenant\Persediaan\AdjustmentProducts;
use App\Models\Tenant\Persediaan\Inventory;
use App\Models\Tenant\Persediaan\StockAwal;
use App\Models\Tenant\Persediaan\StockUsage;
use App\Models\Tenant\Persediaan\StockUsageProduct;
use Illuminate\Database\Eloquent\Model;

class Coa extends Model
{
    protected $table = 'als_coa';
    protected $guarded = [];
    public $timestamps = false;

    public static $rules = [
        'coa_name' => 'required',
        'head_coa' => 'required',
    ];
    /**
     * Rules untuk proses UPDATE
     */
    public static $updateRules = [
        'coa_name' => 'required',
        'head_coa' => 'nullable',
        // coa_code juga akan ditambahkan di FormRequest (karena butuh unique:ignore)
    ];



    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($unit) {
            if ($unit->canDelete()) {
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
        if ($this->stockawal()->count() > 0 || $this->adjustment()->count() > 0 || $this->adjustmentproduct()->count() > 0 || $this->bukupembantu()->count() > 0 || $this->stockusage()->count() > 0 || $this->stockusageproduct()->count() > 0 || $this->inventory()->count() > 0 || $this->jurnal()->count() > 0 || $this->payment()->count() || $this->coaproduct()->count() > 0 || $this->coabiaya()->count() > 0 || $this->purchasedownpayment()->count() > 0 || $this->purchaseinvoice()->count() > 0 || $this->jurnaltransactions()->count() > 0 || $this->purchaseorder()->count() || $this->purchasepaymentinvoicediscount()->count() > 0 || $this->purchasepaymentinvoiceoverpay()->count() > 0 || $this->salesdownpayment()->count() > 0 || $this->salesinvoice()->count() > 0 || $this->salespaymentinvoicediscount()->count() > 0 || $this->salespaymentinvoiceoverpay()->count() > 0 || $this->vendor()->count() > 0 || $this->taxpurchase()->count() > 0 || $this->taxsales()->count() > 0) {
            return false; // Deletion not allowed
        }

        return true; // Deletion allowed
    }

    public function stockawal()
    {
        return $this->hasMany(StockAwal::class, 'coa_id');
    }

    public function adjustment(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Adjustment::class,'coa_adjustment_id');
    }

    public function adjustmentproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AdjustmentProducts::class,'coa_id');
    }

    public function bukupembantu(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BukuPembantu::class, 'coa_id');
    }

    public function inventory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Inventory::class, 'coa_id');
    }

    public function jurnal(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Jurnal::class, 'coa_id');
    }

    public function payment(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentMethod::class, 'coa_id');
    }

    public function coaproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class, 'coa_id');
    }
    public function coabiaya(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class, 'coa_biaya_id');
    }

    public function purchasedownpayment(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseDownPayment::class, 'coa_id');
    }

    public function purchaseinvoice(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseInvoicing::class, 'coa_id');
    }

    public function jurnaltransactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(JurnalTransaksi::class,'coa_id');
    }

    public function purchaseorder(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'coa_id');
    }

    public function purchasepaymentinvoicediscount(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchasePaymentInvoice::class, 'coa_id_discount');
    }

    public function purchasepaymentinvoiceoverpay(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchasePaymentInvoice::class, 'coa_id_overpayment');
    }

    public function salesdownpayment(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesDownpayment::class, 'coa_id');
    }

    public function salesinvoice(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesInvoicing::class, 'coa_id');
    }

    public function salespaymentinvoicediscount(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesPaymentInvoice::class, 'coa_id_discount');
    }

    public function salespaymentinvoiceoverpay(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesPaymentInvoice::class, 'coa_id_overpayment');
    }

    public function stockusage(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockUsage::class, 'coa_id');
    }

    public function stockusageproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockUsageProduct::class, 'coa_id');
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Vendor::class, 'coa_id');
    }

    public function taxpurchase(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Tax::class, 'purchase_coa_id');
    }

    public function taxsales(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Tax::class, 'sales_coa_id');
    }

    public function children()
    {
        return $this->hasMany(Coa::class, 'coa_parent');
    }
}
