<?php

namespace Icso\Accounting\Repositories\AsetTetap\Pembelian;

use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseReceive;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseReceiveMeta;
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

class ReceiveRepo extends ElequentRepository
{
    protected $model;

    public function __construct(PurchaseReceive $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('receive_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhereHas('order', function ($query) use ($search) {
                $query->where('nama_aset', 'like', '%' .$search. '%');
                $query->orWhere('no_aset', 'like', '%' .$search. '%');
            });
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
        })->with(['order','order.aset_tetap_coa'])->orderBy('receive_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('receive_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhereHas('order', function ($query) use ($search) {
                $query->where('nama_aset', 'like', '%' .$search. '%');
                $query->orWhere('no_aset', 'like', '%' .$search. '%');
            });
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
        })->orderBy('receive_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        $id = $request->id;
        $receivedNo = $request->receive_no;
        if(empty($receivedNo)){
            $receivedNo = self::generateCodeTransaction(new PurchaseReceive(),KeyNomor::NO_PENERIMAAN_PEMBELIAN_ASET_TETAP,'receive_no','receive_date');
        }
        $receivedDate = !empty($request->received_date) ? Utility::changeDateFormat($request->receive_date) : date("Y-m-d");
        $note = !empty($request->note) ? $request->note : "";
        $orderId = $request->order_id;
        $userId = $request->user_id;
        $susutNow = !empty($request->susut_skrg) ? $request->susut_skrg : 0;
        $penyusutanDate = !empty($request->susut_skrg) ? date('Y-m-d') : null;
        $receiveData = array(
            'receive_date' => $receivedDate,
            'receive_no' => $receivedNo,
            'note' => $note,
            'updated_by' => $userId,
            'order_id' => $orderId,
            'susut_skrg' => $susutNow,
            'penyusutan_date' => $penyusutanDate,
            'reason' => '',
            'updated_at' => date('Y-m-d H:i:s'),
        );
        DB::beginTransaction();
        try {
            if (empty($id)) {
                $receiveData['created_at'] = date('Y-m-d H:i:s');
                $receiveData['created_by'] = $userId;
                $receiveData['receive_status'] = StatusEnum::OPEN;
                $res = $this->create($receiveData);
            } else {
                $res = $this->update($receiveData, $id);
            }
            if ($res) {
                if (!empty($id)) {
                    $this->deleteAdditional($id);
                    $recId = $id;
                } else {
                    $recId = $res->id;
                }
                $this->postingJurnal($recId);
                OrderRepo::changeStatus($orderId, StatusEnum::PENERIMAAN);
                $fileUpload = new FileUploadService();
                $uploadedFiles = $request->file('files');
                if(!empty($uploadedFiles)) {
                    if (count($uploadedFiles) > 0) {
                        foreach ($uploadedFiles as $file) {
                            // Handle each file as needed
                            $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                            if ($resUpload) {
                                $arrUpload = array(
                                    'receive_id' => $recId,
                                    'meta_key' => 'upload',
                                    'meta_value' => $resUpload
                                );
                                PurchaseReceiveMeta::create($arrUpload);
                            }
                        }
                    }
                }
                DB::commit();
                return true;
            }else {
                return false;
            }
        }catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function deleteAdditional($id)
    {
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PENERIMAAN_ASET_TETAP, $id);
        PurchaseReceiveMeta::where(array('receive_id' => $id))->delete();
    }

    public static function changeStatusByOrderId($orderId,$status=StatusEnum::OPEN)
    {
        $res = PurchaseReceive::where(array('order_id' => $orderId))->first();
        if($res){
            $res->receive_status = $status;
            $res->save();
        }

    }

    public function postingJurnal($id)
    {
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $coaBebanDiBayarDiMuka = SettingRepo::getOptionValue(SettingEnum::COA_BEBAN_DIBAYAR_DIMUKA);
        $res = $this->findOne($id, array(), ['order']);
        if(!empty($res)){
            $resOrder = $res->order;
            if(!empty($resOrder)){
                $coaAsetTetapId = $resOrder->aset_tetap_coa_id;
                $nominal = $resOrder->dpp;

                //jurnal debet
                $arrJurnalDebet = array(
                    'transaction_date' => $res->receive_date,
                    'transaction_datetime' => $res->receive_date." ".date('H:i:s'),
                    'created_by' => $res->created_by,
                    'updated_by' => $res->created_by,
                    'transaction_code' => TransactionsCode::PENERIMAAN_ASET_TETAP,
                    'coa_id' => $coaAsetTetapId,
                    'transaction_id' => $res->id,
                    'transaction_sub_id' => 0,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_no' => $res->receive_no,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'debet' => $nominal,
                    'kredit' => 0,
                    'note' => !empty($res->note) ? $res->note : 'Penerimaan pembelian aset tetap',
                );
                $jurnalTransaksiRepo->create($arrJurnalDebet);

                //jurnal kredit beban dibayar dimuka
                $arrJurnalKredit = array(
                    'transaction_date' => $res->receive_date,
                    'transaction_datetime' => $res->receive_date." ".date('H:i:s'),
                    'created_by' => $res->created_by,
                    'updated_by' => $res->created_by,
                    'transaction_code' => TransactionsCode::PENERIMAAN_ASET_TETAP,
                    'coa_id' => $coaBebanDiBayarDiMuka,
                    'transaction_id' => $res->id,
                    'transaction_sub_id' => 0,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_no' => $res->receive_no,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'debet' => 0,
                    'kredit' => $nominal,
                    'note' => !empty($res->note) ? $res->note : 'Penerimaan pembelian aset tetap',
                );
                $jurnalTransaksiRepo->create($arrJurnalKredit);
            }

        }
    }
}
