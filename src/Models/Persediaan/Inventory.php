<?php
namespace Icso\Accounting\Models\Persediaan;


use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Unit;
use Icso\Accounting\Models\Master\Warehouse;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'als_inventory';
    protected $guarded = [];
    public $timestamps = false;
    public static function getTableName()
    {
        return with(new static)->getTable();
    }
    public function product()
    {
        return $this->belongsTo(Product::class,'product_id');
    }

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class,'warehouse_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
