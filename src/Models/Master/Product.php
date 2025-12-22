<?php
namespace Icso\Accounting\Models\Master;


use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Models\Persediaan\StockAwal;
use Icso\Accounting\Models\Persediaan\StockUsageProduct;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'als_product';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['images'];

    public static $rules = [
        'item_name' => 'required',
        'unit_id' => 'required',
        'category' => 'required',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($product) {
            if ($product->canDelete()) {
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
        if ($this->productconvertion()->count() > 0 || $this->productstockusage()->count() > 0 || $this->stockawal()->count() > 0 || $this->inventory()->count() > 0) {
            return false; // Deletion not allowed
        }

        return true; // Deletion allowed
    }

    public static function getTableName()
    {
        return with(new static)->getTable();
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function categories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'als_product_category',
            'product_id', 'category_id');
    }

    public function coa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function coa_biaya(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class,'coa_biaya_id');
    }

    public function productconvertion(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductConvertion::class, 'product_id');
    }


    public function productstockusage(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StockUsageProduct::class, 'product_id');
    }

    public function stockawal()
    {
        return $this->hasMany(StockAwal::class, 'product_id');
    }

    public function inventory(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Inventory::class, 'product_id');
    }

    public function productmeta()
    {
        return $this->hasMany(ProductMeta::class, 'product_id');
    }

    public function getImagesAttribute()
    {
        $baseUrl = url('storage/'.tenant()->id.'/app/public/');
        $res = ProductMeta::where('product_id', $this->id)->where('meta_key','upload')->get();
        // Modify each meta_value to include the base URL
        $res->each(function ($item) use ($baseUrl) {
            $item->meta_value = $baseUrl . '/' . $item->meta_value;
        });

        return $res;
    }


}
