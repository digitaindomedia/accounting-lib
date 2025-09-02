<?php
namespace Icso\Accounting\Models\Master;

use Icso\Accounting\Models\Persediaan\AdjustmentProducts;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Models\Persediaan\StockAwal;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $table = 'als_unit';
    protected $guarded = [];
    public $timestamps = false;

    public static $rules = [
        'unit_name' => 'required',
        'unit_code' => 'required',
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
        if ($this->products()->count() > 0 || $this->productconvertion()->count() > 0 || $this->stockawal()->count() > 0 || $this->adjustmentproduct()->count() > 0 || $this->inventory()->count() > 0) {
            return false; // Deletion not allowed
        }

        return true; // Deletion allowed
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'unit_id');
    }

    public function productconvertion()
    {
        return $this->hasMany(ProductConvertion::class, 'unit_id');
    }

    public function stockawal()
    {
        return $this->hasMany(StockAwal::class, 'unit_id');
    }

    public function adjustmentproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AdjustmentProducts::class,'unit_id');
    }

    public function inventory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Inventory::class, 'unit_id');
    }
}
