<?php
namespace Icso\Accounting\Models\Master;


use Icso\Accounting\Models\AsetTetap\Pembelian\PurchasePayment;
use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPayment;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $table = 'als_payment';
    protected $guarded = [];
    public $timestamps = false;

    public static $rules = [
        'payment_name' => 'required',
        'coa_id' => 'required',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($payment) {
            if ($payment->canDelete()) {
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
        if ($this->purchasepayment()->count() > 0 || $this->salespayment()->count() > 0) {
            return false; // Deletion not allowed
        }

        return true; // Deletion allowed
    }

    public function purchasepayment()
    {
        return $this->hasMany(PurchasePayment::class, 'payment_method_id');
    }

    public function salespayment()
    {
        return $this->hasMany(SalesPayment::class, 'payment_method_id');
    }

    public function coa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }
}
