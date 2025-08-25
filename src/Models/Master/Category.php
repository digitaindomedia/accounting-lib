<?php
namespace Icso\Accounting\Models\Master;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'als_category';
    protected $guarded = [];
    public $timestamps = false;

    public static $rules = [
        'category_name' => 'required'
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($category) {
            if ($category->canDelete()) {
                // Deletion is allowed
            } else {
                // Deletion is not allowed
                return false; // This prevents the record from being deleted
            }
        });
    }

    public function productcategory()
    {
        return $this->hasMany(ProductCategory::class, 'category_id');
    }

    public function canDelete()
    {
        // Check if there are associated comments
        if ($this->productcategory()->count() > 0) {
            return false; // Deletion not allowed
        }

        return true; // Deletion allowed
    }
}
