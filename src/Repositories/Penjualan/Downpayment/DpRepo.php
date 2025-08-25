<?php

namespace Icso\Accounting\Repositories\Penjualan\Downpayment;

use App\Enums\JurnalStatusEnum;
use App\Enums\SettingEnum;
use App\Enums\StatusEnum;
use App\Enums\TypeEnum;
use App\Models\Tenant\Akuntansi\JurnalTransaksi;
use App\Models\Tenant\Pembelian\UangMuka\PurchaseDownPayment;
use App\Models\Tenant\Penjualan\Pengiriman\SalesDeliveryMeta;
use App\Models\Tenant\Penjualan\UangMuka\SalesDownpayment;
use App\Models\Tenant\Penjualan\UangMuka\SalesDownPaymentMeta;
use App\Repositories\ElequentRepository;
use App\Repositories\Tenant\Akuntansi\JurnalTransaksiRepo;
use App\Repositories\Tenant\Utils\SettingRepo;
use App\Services\FileUploadService;
use App\Utils\Helpers;
use App\Utils\KeyNomor;
use App\Utils\TransactionsCode;
use App\Utils\Utility;
use App\Utils\VarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DpRepo extends ElequentRepository
{
    protected $model;

    public function __construct(SalesDownpayment $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    if($metod == 'whereBetween'){
                        $que->$metod($item['value']['field'], $item['value']['value']);
                    } else {
                        $que->$metod($item['value']);
                    }
                }
            });
        })->with(['order','coa','order.vendor'])->orderBy('downpayment_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    if($metod == 'whereBetween'){
                        $que->$metod($item['value']['field'], $item['value']['value']);
                    } else {
                        $que->$metod($item['value']);
                    }
                }
            });
        })->orderBy('downpayment_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $userId = $request->user_id;
        $refNo = $request->ref_no;
        if (empty($refNo)) {
            $refNo = self::generateCodeTransaction(new SalesDownpayment(), KeyNomor::NO_UANG_MUKA_PENJUALAN, 'ref_no', 'downpayment_date');
        }
        $note = $request->note;
        $orderId = $request->order_id;
        $coaId = !empty($request->coa_id) ? $request->coa_id : 0;
        $taxId = !empty($request->tax_id) ? $request->tax_id : 0;
        $dpType = $request->dp_type;
        $nominal = Utility::remove_commas($request->nominal);
        $dpDate = !empty($request->downpayment_date) ? Utility::changeDateFormat($request->downpayment_date) : date("Y-m-d");
        $arrData = array(
            'ref_no' => $refNo,
            'downpayment_date' => $dpDate,
            'nominal' => $nominal,
            'note' => $note,
            'order_id' => $orderId,
            'coa_id' => $coaId,
            'tax_id' => $taxId,
            'tax_percentage' => $request->tax_percentage,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s')
        );
        DB::beginTransaction();
        try {
            if (empty($id)) {
                $arrData['downpayment_status'] = StatusEnum::OPEN;
                $arrData['reason'] = "";
                $arrData['dp_type'] = $dpType;
                $arrData['document'] = "";
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $arrData['created_by'] = $userId;
                $res = $this->create($arrData);
            } else {
                $res = $this->update($arrData, $id);
            }
            if ($res) {
                if (!empty($id)) {
                    $this->deleteAdditional($id);
                    $resId = $id;
                } else {
                    $resId = $res->id;
                }
                $this->postingJurnal($resId);
                $fileUpload = new FileUploadService();
                $uploadedFiles = $request->file('files');
                if(!empty($uploadedFiles)) {
                    if (count($uploadedFiles) > 0) {
                        foreach ($uploadedFiles as $file) {
                            // Handle each file as needed
                            $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                            if ($resUpload) {
                                $arrUpload = array(
                                    'dp_id' => $resId,
                                    'meta_key' => 'upload',
                                    'meta_value' => $resUpload
                                );
                                SalesDownPaymentMeta::create($arrUpload);
                            }
                        }
                    }
                }
                DB::commit();
                return true;
            } else {
                DB::rollBack();
                return false;
            }
        }catch (\Exception $e) {
            echo $e->getMessage();
            DB::rollBack();
            return false;
        }
    }

    public function deleteAdditional($id)
    {
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::UANG_MUKA_PENJUALAN, $id);
        SalesDownPaymentMeta::where(array('dp_id' => $id))->delete();
    }

    public function postingJurnal($idUangMuka){
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $res = $this->findOne($idUangMuka, array(), ['order', 'order.vendor', 'tax', 'tax.taxgroup','tax.taxgroup.tax']);
        if(!empty($res)){
            $nominal = $res->nominal;
            $dpp = $nominal;
            $arrTax = array();
            if($res->tax_id != "0")
            {
                $objTax = $res->tax;
                if(!empty($objTax)) {
                    if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
                        $getCalcTax = Helpers::hitungIncludeTax($objTax->tax_percentage,$nominal);
                        $ppn = $getCalcTax[TypeEnum::PPN];
                        $posisi = "kredit";
                        if ($objTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                            $posisi = "debet";
                            $dpp = $dpp + $ppn;
                        } else {
                            $dpp = $dpp - $ppn;
                        }
                        $arrTax[] = array(
                            'coa_id' => $objTax->sales_coa_id,
                            'posisi' => $posisi,
                            'nominal' => $ppn,
                            'id_item' => $res->id
                        );
                    }
                    else {
                        $tagGroups = $objTax->taxgroup;
                        if (!empty($tagGroups)) {
                            $total = $nominal;
                            foreach ($tagGroups as $group) {
                                $findTax = $group->tax;
                                if (!empty($findTax)) {
                                    $pembagi = ($findTax->tax_percentage + 100) / 100;
                                    $subtotal = $total / $pembagi;
                                    $tax = ($findTax->tax_percentage / 100) * $subtotal;
                                    $posisi = "kredit";
                                    if ($findTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                                        $posisi = "debet";
                                        $dpp = $dpp + $tax;
                                    } else {
                                        $dpp = $dpp - $tax;
                                    }
                                    $arrTax[] = array(
                                        'coa_id' => $findTax->sales_coa_id,
                                        'posisi' => $posisi,
                                        'nominal' => $tax,
                                        'id_item' => $res->id
                                    );
                                }
                            }
                        }
                    }
                }

            }
            $coaUangMukaPenjualan = SettingRepo::getOptionValue(SettingEnum::COA_UANG_MUKA_PENJUALAN);
            $coaKasBank = SettingRepo::getOptionValue(SettingEnum::COA_KAS_BANK);
            if(!empty($res->coa_id)){
                $coaKasBank = $res->coa_id;
            }
            $customerName = "";
            if(!empty($res->order->vendor)){
                $customerName =   !empty($res->order->vendor->vendor_company_name) ? $res->order->vendor->vendor_company_name : $res->order->vendor->vendor_name;
            }
            $noteCustomer = !empty($supplierName) ? " customer dengan nama ".$customerName : "";

            //jurnal debet
            $arrJurnalDebet = array(
                'transaction_date' => $res->downpayment_date,
                'transaction_datetime' => $res->downpayment_date." ".date('H:i:s'),
                'created_by' => $res->created_by,
                'updated_by' => $res->created_by,
                'transaction_code' => TransactionsCode::UANG_MUKA_PENJUALAN,
                'coa_id' => $coaKasBank,
                'transaction_id' => $res->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $res->ref_no,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => $nominal,
                'kredit' => 0,
                'note' => !empty($res->note) ? $res->note : 'Uang muka penjualan '.$noteCustomer,
            );
            $jurnalTransaksiRepo->create($arrJurnalDebet);

            //jurnal kredit
            $arrJurnalKredit = array(
                'transaction_date' => $res->downpayment_date,
                'transaction_datetime' => $res->downpayment_date." ".date('H:i:s'),
                'created_by' => $res->created_by,
                'updated_by' => $res->created_by,
                'transaction_code' => TransactionsCode::UANG_MUKA_PENJUALAN,
                'coa_id' => $coaUangMukaPenjualan,
                'transaction_id' => $res->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $res->ref_no,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => 0,
                'kredit' => $dpp,
                'note' => !empty($res->note) ? $res->note : 'Uang muka penjualan '.$noteCustomer,
            );
            $jurnalTransaksiRepo->create($arrJurnalKredit);
            if(!empty($arrTax)){
                if(count($arrTax) > 0){
                    foreach ($arrTax as $val){
                        if($val['posisi'] == 'debet'){
                            $arrJurnalDebet = array(
                                'transaction_date' => $res->downpayment_date,
                                'transaction_datetime' => $res->downpayment_date." ".date('H:i:s'),
                                'created_by' => $res->created_by,
                                'updated_by' => $res->created_by,
                                'transaction_code' => TransactionsCode::UANG_MUKA_PENJUALAN,
                                'coa_id' => $val['coa_id'],
                                'transaction_id' => $res->id,
                                'transaction_sub_id' => 0,
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                                'transaction_no' => $res->ref_no,
                                'transaction_status' => JurnalStatusEnum::OK,
                                'debet' => $val['nominal'],
                                'kredit' => 0,
                                'note' => !empty($res->note) ? $res->note : 'Uang muka penjualan '.$noteCustomer,
                            );
                            $jurnalTransaksiRepo->create($arrJurnalDebet);
                        } else
                        {
                            $arrJurnalKredit = array(
                                'transaction_date' => $res->downpayment_date,
                                'transaction_datetime' => $res->downpayment_date." ".date('H:i:s'),
                                'created_by' => $res->created_by,
                                'updated_by' => $res->created_by,
                                'transaction_code' => TransactionsCode::UANG_MUKA_PENJUALAN,
                                'coa_id' => $val['coa_id'],
                                'transaction_id' => $res->id,
                                'transaction_sub_id' => 0,
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                                'transaction_no' => $res->ref_no,
                                'transaction_status' => JurnalStatusEnum::OK,
                                'debet' => 0,
                                'kredit' => $val['nominal'],
                                'note' => !empty($res->note) ? $res->note : 'Uang muka penjualan '.$noteCustomer,
                            );
                            $jurnalTransaksiRepo->create($arrJurnalKredit);
                        }
                    }
                }

            }

        }
    }

    public function getTotalUangMukaByOrderId($idOrder)
    {
        $nominal = SalesDownpayment::where(array('order_id' => $idOrder))->sum('nominal');
        return $nominal;
    }

    public static function changeStatusUangMuka($idUangMuka, $statusUangMuka=StatusEnum::SELESAI)
    {
        $instance = (new self(new SalesDownpayment()));
        $arrUpdateStatus = array(
            'downpayment_status' => $statusUangMuka
        );
        $instance->update($arrUpdateStatus, $idUangMuka);
    }
}
