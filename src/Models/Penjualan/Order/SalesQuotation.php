<?php

namespace Icso\Accounting\Models\Penjualan\Order;

use Illuminate\Database\Eloquent\Model;

class SalesQuotation extends Model
{
    protected $table = 'als_sales_quotation';
    protected $guarded = [];
    public $timestamps = false;

    public function quotationproduct()
    {
        return $this->hasMany(SalesQuotationProduct::class, 'quotation_id');
    }
}
