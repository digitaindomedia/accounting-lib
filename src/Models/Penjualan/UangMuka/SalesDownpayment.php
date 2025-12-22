<?php

namespace Icso\Accounting\Models\Penjualan\UangMuka;


use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Models\Master\Tax;
use Icso\Accounting\Models\Penjualan\Order\SalesOrder;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Database\Eloquent\Model;

class SalesDownpayment extends Model
{
    protected $table = 'als_sales_downpayment';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name','attachments'];

    public static $rules = [
        'downpayment_date' => 'required',
        'nominal' => 'required|numeric|gt:0',
        'order_id' => 'required',
        'coa_id' => 'required',
    ];

    public function getCreatedByNameAttribute()
    {
        return Helpers::getNamaUser($this->created_by);
    }

    /*public function getCreatedByAttribute($value)
    {
        return Helpers::getNamaUser($value);
    }*/

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function coa()
    {
        return $this->belongsTo(Coa::class, 'coa_id');
    }

    public function tax(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tax::class,'tax_id');
    }

    public function downpaymentmeta(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesDownPaymentMeta::class, 'dp_id');
    }

    public function getAttachmentsAttribute()
    {
        $baseUrl = url('storage/'.tenant()->id.'/app/public/');
        $res = SalesDownPaymentMeta::where('dp_id', $this->id)->where('meta_key','upload')->get();
        // Modify each meta_value to include the base URL
        $res->each(function ($item) use ($baseUrl) {
            $item->meta_value = $baseUrl . '/' . $item->meta_value;
        });

        return $res;
    }
}
