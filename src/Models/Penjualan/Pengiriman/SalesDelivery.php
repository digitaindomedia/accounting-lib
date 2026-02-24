<?php

namespace Icso\Accounting\Models\Penjualan\Pengiriman;

use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Models\Penjualan\Order\SalesOrder;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Database\Eloquent\Model;

class SalesDelivery extends Model
{
    protected $table = 'als_sales_delivery';
    protected $guarded = [];
    public $timestamps = false;
    protected $appends = ['created_by_name','attachments'];

    public static $rules = [
        'delivery_date' => 'required',
        'order_id' => 'required',
        'vendor_id' => 'required',
        'warehouse_id' => 'required',
        'deliveryproduct' => 'required'
    ];

    public function getCreatedByNameAttribute()
    {
        return Helpers::getNamaUser($this->created_by);
    }

   /* public function getCreatedByAttribute($value)
    {
        return Helpers::getNamaUser($value);
    }*/

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function deliveryproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesDeliveryProduct::class, 'delivery_id');
    }


    public function getAttachmentsAttribute()
    {
        $baseUrl = url('storage/'.tenant()->id.'/app/public/');
        $res = SalesDeliveryMeta::where('delivery_id', $this->id)->where('meta_key','upload')->get();
        // Modify each meta_value to include the base URL
        $res->each(function ($item) use ($baseUrl) {
            $item->meta_value = $baseUrl . '/' . $item->meta_value;
        });

        return $res;
    }

    public function shippingAddress(): ?string
    {
        return SalesDeliveryMeta::where('delivery_id', $this->id)
            ->where('meta_key', 'shipping_address')
            ->value('meta_value');
    }
}
