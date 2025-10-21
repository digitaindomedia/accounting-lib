<?php

namespace Icso\Accounting\Models\Penjualan\Order;

use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Penjualan\UangMuka\SalesDownpayment;
use Icso\Accounting\Repositories\Penjualan\Downpayment\DpRepo;
use Icso\Accounting\Repositories\Penjualan\Order\SalesOrderRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $table = 'als_sales_order';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['created_by_name', 'has_dp','total_dp','available_delivery'];

    public static $rules = [
        'order_date' => 'required',
        'vendor_id' => 'required',
        'orderproduct' => 'required'
    ];

    public function getCreatedByNameAttribute()
    {
        return Helpers::getNamaUser($this->created_by);
    }

    /*public function getCreatedByAttribute($value)
    {
        return Helpers::getNamaUser($value);
    }*/

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function orderproduct(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesOrderProduct::class,'order_id');
    }

    public function ordermeta()
    {
        return $this->hasMany(SalesOrderMeta::class,'order_id');
    }

    public function getHasDpAttribute()
    {
        $countDP = SalesDownpayment::where(array('order_id' => $this->id, 'downpayment_status' => StatusEnum::OPEN))->count();
        $hasDp = false;
        if($countDP > 0)
        {
            $hasDp = true;
        }
        return $hasDp;
    }

    public function getTotalDpAttribute()
    {
        $salesDpRepo = new DpRepo(new SalesDownpayment());
        $getNominalDp = $salesDpRepo->getTotalUangMukaByOrderId($this->id);
        return $getNominalDp;
    }

    public function getAvailableDeliveryAttribute()
    {
        $salesOrderRepo = new SalesOrderRepo(new SalesOrder());
        $resDelivery = $salesOrderRepo->findInUseInDeliveryOrSpkById($this->id);
        return $resDelivery['order_product'];
    }
}
