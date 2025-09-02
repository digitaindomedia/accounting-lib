<?php

namespace Icso\Accounting\Repositories\Pembelian\Downpayment;


use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Pembelian\UangMuka\PurchaseDownPayment;
use Icso\Accounting\Models\Pembelian\UangMuka\PurchaseDownPaymentMeta;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Icso\Accounting\Utils\VarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DpRepo extends ElequentRepository
{
    protected $model;

    public function __construct(PurchaseDownPayment $model)
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
            $query->orWhere('no_faktur', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->with(['order','coa'])->orderBy('downpayment_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhere('no_faktur', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('downpayment_date','desc')->count();
        return $dataSet;
    }

    public function getAllDataBetweenBy($search, $page, $perpage, array $where = [], array $whereBetween=[])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhere('no_faktur', 'like', '%' .$search. '%');
            $query->orWhereHas('order', function ($query) use ($search) {
                $query->where('order_no', 'like', '%' .$search. '%');
            });
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
            $query->orWhereHas('order', function ($query) use ($where) {
                $query->where($where);
            });
        })->when(!empty($whereBetween), function ($query) use($whereBetween){
            $query->whereBetween('downpayment_date', $whereBetween);
        })->orderBy('downpayment_date','desc')->with(['order','order.vendor','coa'])->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBetweenBy($search, array $where = [], array $whereBetween=[])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhere('no_faktur', 'like', '%' .$search. '%');
            $query->orWhereHas('order', function ($query) use ($search) {
                $query->where('order_no', 'like', '%' .$search. '%');
            });
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
            $query->orWhereHas('order', function ($query) use ($where) {
                $query->where($where);
            });
        })->when(!empty($whereBetween), function ($query) use($whereBetween){
            $query->whereBetween('downpayment_date', $whereBetween);
        })->orderBy('downpayment_date','desc')->with(['order','order.vendor','coa'])->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $userId = $request->user_id;
        $refNo = $request->ref_no;
        if(empty($refNo)){
            $refNo = self::generateCodeTransaction(new PurchaseDownPayment(),KeyNomor::NO_UANG_MUKA_PEMBELIAN,'ref_no','downpayment_date');
        }
        $fakturAccepted = !empty($request->faktur_accepted) ? $request->faktur_accepted : 'no';
        $noFaktur = $request->no_faktur;
        $note = $request->note;
        $orderId = $request->order_id;
        $coaId = !empty($request->coa_id) ? $request->coa_id : 0;
        $taxId = !empty($request->tax_id) ? $request->tax_id : 0;
        $dpType = $request->dp_type;
        $nominal = Utility::remove_commas($request->nominal);
        $dpDate = !empty($request->downpayment_date) ? Utility::changeDateFormat($request->downpayment_date) : date("Y-m-d");
        $fakturDate = !empty($request->faktur_date) ? Utility::changeDateFormat($request->faktur_date) : date('Y-m-d');
        $arrData = array(
            'ref_no' => $refNo,
            'downpayment_date' => $dpDate,
            'faktur_date' => $fakturDate,
            'nominal' => $nominal,
            'faktur_accepted' => $fakturAccepted,
            'no_faktur' => $noFaktur,
            'note' => $note,
            'order_id' => $orderId,
            'coa_id' => $coaId,
            'tax_id' => $taxId,
            'dp_type' => $dpType,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s')
        );
        DB::beginTransaction();
        try {
            if (empty($id)) {
                $arrData['downpayment_status'] = StatusEnum::OPEN;
                $arrData['reason'] = "";
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
                                PurchaseDownPaymentMeta::create($arrUpload);
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
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function postingJurnal($idUangMuka){
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $res = $this->findOne($idUangMuka, array(), ['order', 'order.vendor', 'tax', 'tax.taxgroup','tax.taxgroup.tax']);
        if(!empty($res)){
            $nominal = $res->nominal;
            $dpp = $nominal;
            $arrTax = array();
            if($res->faktur_accepted == TypeEnum::FAKTUR_ACCEPTED)
            {
                $objTax = $res->tax;
                if(!empty($objTax)) {
                    if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
                        if($objTax->is_dpp_nilai_Lain == 1){
                            $getCalcTax = Helpers::hitungIncludeTaxDppNilaiLain($objTax->tax_percentage,$nominal);
                        } else {
                            $getCalcTax = Helpers::hitungIncludeTax($objTax->tax_percentage,$nominal);
                        }

                        $ppn = $getCalcTax[TypeEnum::PPN];
                        $posisi = "debet";
                        if ($objTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                            $posisi = "kredit";
                            $dpp = $dpp + $ppn;
                        } else {
                            $dpp = $dpp - $ppn;
                        }
                        $arrTax[] = array(
                            'coa_id' => $objTax->purchase_coa_id,
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
                                    if($objTax->is_dpp_nilai_Lain == 1){
                                        $getCalcTax = Helpers::hitungIncludeTaxDppNilaiLain($findTax->tax_percentage,$total);
                                    } else {
                                        $getCalcTax = Helpers::hitungIncludeTax($findTax->tax_percentage,$total);
                                    }
                                    $tax = $getCalcTax[TypeEnum::PPN];
                                    $posisi = "debet";
                                    if ($findTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                                        $posisi = "kredit";
                                        $dpp = $dpp + $tax;
                                    } else {
                                        $dpp = $dpp - $tax;
                                    }
                                    $arrTax[] = array(
                                        'coa_id' => $findTax->purchase_coa_id,
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
            $coaUangMukaPembelian = SettingRepo::getOptionValue(SettingEnum::COA_UANG_MUKA_PEMBELIAN);
            $coaPpnMasukan = SettingRepo::getOptionValue(SettingEnum::COA_PPN_MASUKAN);
            $coaKasBank = SettingRepo::getOptionValue(SettingEnum::COA_KAS_BANK);
            if(!empty($res->coa_id)){
                $coaKasBank = $res->coa_id;
            }
            $supplierName = "";
            if(!empty($res->order->vendor)){
                $supplierName =   !empty($res->order->vendor->vendor_company_name) ? $res->order->vendor->vendor_company_name : $res->order->vendor->vendor_name;
            }
            $noteSupplier = !empty($supplierName) ? " supplier dengan nama ".$supplierName : "";
            $arrJurnalDebet = array(
                'transaction_date' => $res->downpayment_date,
                'transaction_datetime' => $res->downpayment_date." ".date('H:i:s'),
                'created_by' => $res->created_by,
                'updated_by' => $res->created_by,
                'transaction_code' => TransactionsCode::UANG_MUKA_PEMBELIAN,
                'coa_id' => $coaUangMukaPembelian,
                'transaction_id' => $res->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $res->ref_no,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => $dpp,
                'kredit' => 0,
                'note' => !empty($res->note) ? $res->note : 'Uang muka pembelian '.$noteSupplier,
            );
            $jurnalTransaksiRepo->create($arrJurnalDebet);
            if(!empty($arrTax)){
                if(count($arrTax) > 0){
                    foreach ($arrTax as $val){
                        if($val['posisi'] == 'debet'){
                            $arrJurnalDebet = array(
                                'transaction_date' => $res->downpayment_date,
                                'transaction_datetime' => $res->downpayment_date." ".date('H:i:s'),
                                'created_by' => $res->created_by,
                                'updated_by' => $res->created_by,
                                'transaction_code' => TransactionsCode::UANG_MUKA_PEMBELIAN,
                                'coa_id' => $val['coa_id'],
                                'transaction_id' => $res->id,
                                'transaction_sub_id' => 0,
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                                'transaction_no' => $res->ref_no,
                                'transaction_status' => JurnalStatusEnum::OK,
                                'debet' => $val['nominal'],
                                'kredit' => 0,
                                'note' => !empty($res->note) ? $res->note : 'Uang muka pembelian '.$noteSupplier,
                            );
                            $jurnalTransaksiRepo->create($arrJurnalDebet);
                        } else
                        {
                            $arrJurnalKredit = array(
                                'transaction_date' => $res->downpayment_date,
                                'transaction_datetime' => $res->downpayment_date." ".date('H:i:s'),
                                'created_by' => $res->created_by,
                                'updated_by' => $res->created_by,
                                'transaction_code' => TransactionsCode::UANG_MUKA_PEMBELIAN,
                                'coa_id' => $val['coa_id'],
                                'transaction_id' => $res->id,
                                'transaction_sub_id' => 0,
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                                'transaction_no' => $res->ref_no,
                                'transaction_status' => JurnalStatusEnum::OK,
                                'debet' => 0,
                                'kredit' => $val['nominal'],
                                'note' => !empty($res->note) ? $res->note : 'Uang muka pembelian '.$noteSupplier,
                            );
                            $jurnalTransaksiRepo->create($arrJurnalKredit);
                        }
                    }
                }

            }
            $arrJurnalKredit = array(
                'transaction_date' => $res->downpayment_date,
                'transaction_datetime' => $res->downpayment_date." ".date('H:i:s'),
                'created_by' => $res->created_by,
                'updated_by' => $res->created_by,
                'transaction_code' => TransactionsCode::UANG_MUKA_PEMBELIAN,
                'coa_id' => $coaKasBank,
                'transaction_id' => $res->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $res->ref_no,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => 0,
                'kredit' => $nominal,
                'note' => !empty($res->note) ? $res->note : 'Uang muka pembelian '.$noteSupplier,
            );
            $jurnalTransaksiRepo->create($arrJurnalKredit);
        }
    }

    public function deleteAdditional($id)
    {
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::UANG_MUKA_PEMBELIAN, $id);
        PurchaseDownPaymentMeta::where(array('dp_id' => $id))->delete();
    }

    public function deleteData($id)
    {
        DB::beginTransaction();
        try
        {
            $this->deleteAdditional($id);
            $this->delete($id);
            DB::commit();
            return true;
        }
        catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }

    public function getTotalUangMukaByOrderId($idOrder)
    {
        $nominal = PurchaseDownPayment::where(array('order_id' => $idOrder))->sum('nominal');
        return $nominal;
    }

    public static function changeStatusUangMuka($idUangMuka, $statusUangMuka=StatusEnum::SELESAI)
    {
        $instance = (new self(new PurchaseDownPayment()));
        $arrUpdateStatus = array(
            'downpayment_status' => $statusUangMuka
        );
        $instance->update($arrUpdateStatus, $idUangMuka);
    }
}
