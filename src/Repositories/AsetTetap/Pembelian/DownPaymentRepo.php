<?php

namespace Icso\Accounting\Repositories\AsetTetap\Pembelian;

use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseDownPayment;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseDownPaymentMeta;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DownPaymentRepo extends ElequentRepository
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
        if(empty($refNo)){
            $refNo = self::generateCodeTransaction(new PurchaseDownPayment(),KeyNomor::NO_UANG_MUKA_PEMBELIAN_ASET_TETAP,'ref_no','downpayment_date');
        }
        $fakturAccepted = !empty($request->faktur_accepted) ? $request->faktur_accepted : 'no';
        $noFaktur = $request->no_faktur;
        $note = $request->note;
        $orderId = $request->order_id;
        $coaId = !empty($request->coa_id) ? $request->coa_id : 0;
        $taxId = !empty($request->tax_id) ? $request->tax_id : 0;
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
        $res = $this->findOne($idUangMuka, array(), ['order', 'tax', 'tax.taxgroup','tax.taxgroup.tax']);
        $coaUangMukaPembelian = SettingRepo::getOptionValue(SettingEnum::COA_UANG_MUKA_PEMBELIAN_ASET_TETAP);
        $coaPpnMasukan = SettingRepo::getOptionValue(SettingEnum::COA_PPN_MASUKAN);
        $coaKasBank = SettingRepo::getOptionValue(SettingEnum::COA_KAS_BANK);
        if(!empty($res)){
            $nominal = $res->nominal;
            $dpp = $nominal;
            $ppn = 0;
            if($res->faktur_accepted == TypeEnum::FAKTUR_ACCEPTED)
            {
                $dpp = $res->dpp;
                $ppn = $res->ppn;
            }

            if(!empty($res->coa_id)){
                $coaKasBank = $res->coa_id;
            }

            $arrJurnalDebet = array(
                'transaction_date' => $res->downpayment_date,
                'transaction_datetime' => $res->downpayment_date." ".date('H:i:s'),
                'created_by' => $res->created_by,
                'updated_by' => $res->created_by,
                'transaction_code' => TransactionsCode::UANG_MUKA_PEMBELIAN_ASET_TETAP,
                'coa_id' => $coaUangMukaPembelian,
                'transaction_id' => $res->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $res->ref_no,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => $dpp,
                'kredit' => 0,
                'note' => !empty($res->note) ? $res->note : 'Uang muka pembelian aset tetap',
            );
            $jurnalTransaksiRepo->create($arrJurnalDebet);
            if(!empty($ppn)){
                $objTax = $res->tax;
                if(!empty($objTax)) {
                    $coaPpnMasukan = $objTax->purchase_coa_id;
                }
                $arrJurnalDebet = array(
                    'transaction_date' => $res->downpayment_date,
                    'transaction_datetime' => $res->downpayment_date." ".date('H:i:s'),
                    'created_by' => $res->created_by,
                    'updated_by' => $res->created_by,
                    'transaction_code' => TransactionsCode::UANG_MUKA_PEMBELIAN_ASET_TETAP,
                    'coa_id' => $coaPpnMasukan,
                    'transaction_id' => $res->id,
                    'transaction_sub_id' => 0,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_no' => $res->ref_no,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'debet' => $ppn,
                    'kredit' => 0,
                    'note' => !empty($res->note) ? $res->note : 'Uang muka pembelian aset tetap',
                );
                $jurnalTransaksiRepo->create($arrJurnalDebet);
            }

            $arrJurnalKredit = array(
                'transaction_date' => $res->downpayment_date,
                'transaction_datetime' => $res->downpayment_date." ".date('H:i:s'),
                'created_by' => $res->created_by,
                'updated_by' => $res->created_by,
                'transaction_code' => TransactionsCode::UANG_MUKA_PEMBELIAN_ASET_TETAP,
                'coa_id' => $coaKasBank,
                'transaction_id' => $res->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $res->ref_no,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => 0,
                'kredit' => $nominal,
                'note' => !empty($res->note) ? $res->note : 'Uang muka pembelian aset tetap',
            );
            $jurnalTransaksiRepo->create($arrJurnalKredit);
        }
    }

    public function deleteAdditional($id)
    {
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::UANG_MUKA_PEMBELIAN_ASET_TETAP, $id);
        PurchaseDownPaymentMeta::where(array('dp_id' => $id))->delete();
    }

    public static function changeStatus($id,$status=StatusEnum::OPEN)
    {
        $find = PurchaseDownPayment::findOrFail($id);
        $find->downpayment_status = $status;
        $find->save();
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
            Log::error($e->getMessage());
            DB::rollback();
            return false;
        }
    }
}
