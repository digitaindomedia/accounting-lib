<?php

namespace Icso\Accounting\Models\Akuntansi;

use App\Models\Tenant\Pembelian\Invoicing\PurchaseInvoicing;
use App\Models\Tenant\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Utils\VarType;
use Icso\Accounting\Utils\VendorType;
use Illuminate\Database\Eloquent\Model;

class JurnalAkun extends Model
{
    protected $table = 'als_jurnal_akun';
    protected $guarded = [];
    public $timestamps = false;

    protected $appends = ['can_edit', 'can_delete','data_session'];

    public function coa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coa::class, "coa_id");
    }

    public function getCanEditAttribute()
    {
        if(!empty($this->data_sess)){
            $decData = json_decode($this->data_sess);
            if($decData->var_type == VarType::PENAMBAHAN){
                if($decData->var_kontak == VendorType::CUSTOMER){
                    $invoiceNo = $decData->var_no_ref;
                    $findSalesData = SalesInvoicing::where('invoice_no', $invoiceNo)->first();
                    if(!empty($findSalesData)){
                        if($findSalesData->invoice_status == StatusEnum::LUNAS){
                            return false;
                        }
                        else {
                            return true;
                        }
                    } else{
                        return true;
                    }
                } else{
                    $invoiceNo = $decData->var_no_ref;
                    $findPurchaseData = PurchaseInvoicing::where('invoice_no', $invoiceNo)->first();
                    if(!empty($findPurchaseData)){
                        if($findPurchaseData->invoice_status == StatusEnum::LUNAS){
                            return false;
                        }
                        else {
                            return true;
                        }
                    } else{
                        return true;
                    }
                }
            }
        }
        return true;
    }

    public function getCanDeleteAttribute()
    {
        if(!empty($this->data_sess)){
            $decData = json_decode($this->data_sess);
            if($decData->var_type == VarType::PENAMBAHAN){
                if($decData->var_kontak == VendorType::CUSTOMER){
                    $invoiceNo = $decData->var_no_ref;
                    $findSalesData = SalesInvoicing::where('invoice_no', $invoiceNo)->first();
                    if(!empty($findSalesData)){
                        return false;
                    } else{
                        return true;
                    }
                } else{
                    $invoiceNo = $decData->var_no_ref;
                    $findPurchaseData = PurchaseInvoicing::where('invoice_no', $invoiceNo)->first();
                    if(!empty($findPurchaseData)){
                        return false;
                    } else{
                        return true;
                    }
                }
            }
        }
        return true;
    }

    public function getDataSessionAttribute()
    {
        if(!empty($this->data_sess)){
            return json_decode($this->data_sess);
        }
        return "";
    }
}
