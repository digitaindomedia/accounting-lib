<?php

namespace Icso\Accounting\Repositories\AsetTetap\Pembelian;

use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseOrder;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseOrderMeta;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Services\ActivityLogService;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\RequestAuditHelper;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderRepo extends ElequentRepository
{
    protected $model;
    protected ActivityLogService $activityLog;

    public function __construct(PurchaseOrder $model, ActivityLogService $activityLog)
    {
        parent::__construct($model);
        $this->model = $model;
        $this->activityLog = $activityLog;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = []): mixed
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        // $paymentInvoiceRepo = new PaymentInvoiceRepo(new PurchasePaymentInvoice());
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('nama_aset', 'like', '%' .$search. '%');
            $query->orWhere('no_aset', 'like', '%' .$search. '%');
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
        })->with(['aset_tetap_coa', 'dari_akun_coa','akumulasi_penyusutan_coa','penyusutan_coa','downpayment'])->orderBy('aset_tetap_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = []): int
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('nama_aset', 'like', '%' .$search. '%');
            $query->orWhere('no_aset', 'like', '%' .$search. '%');
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
        })->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = []): bool
    {
        $id = $request->id;
        $userId = $request->user_id;
        $oldData = null;
        if (!empty($id)) {
            $oldData = $this->findOne($id, [], [
                'aset_tetap_coa',
                'dari_akun_coa',
                'akumulasi_penyusutan_coa',
                'penyusutan_coa',
                'downpayment'
            ])?->toArray();
        }
        $asetNo = $request->no_aset;
        if(empty($asetNo)){
            $asetNo = self::generateCodeTransaction(new PurchaseOrder(),KeyNomor::NO_ORDER_PEMBELIAN_ASET_TETAP,'no_aset','aset_tetap_date');
        }

        $asetTetapDate = Utility::changeDateFormat($request->aset_tetap_date);

        $arrData = array(
            'no_aset' => $asetNo,
            'nama_aset' => $request->nama_aset,
            'aset_tetap_date' => $asetTetapDate,
            'harga_beli' => Utility::remove_commas($request->harga_beli),
            'aset_tetap_coa_id' => !empty($request->aset_tetap_coa_id) ? $request->aset_tetap_coa_id : 0,
            'dari_akun_coa_id' => !empty($request->dari_akun_coa_id) ? $request->dari_akun_coa_id : 0,
            'note' => !empty($request->note) ? $request->note : '',
            'status_penyusutan' => !empty($request->status_penyusutan) ? $request->status_penyusutan : 0,
            'nilai_penyusutan' => !empty($request->nilai_penyusutan) ? $request->nilai_penyusutan : 0,
            'akumulasi_penyusutan_coa_id' => !empty($request->akumulasi_penyusutan_coa_id) ? $request->akumulasi_penyusutan_coa_id : 0,
            'penyusutan_coa_id' => !empty($request->penyusutan_coa_id) ? $request->penyusutan_coa_id : 0,
            'metode_penyusutan' => !empty($request->metode_penyusutan) ? $request->metode_penyusutan : '',
            'tanggal_mulai_penyusutan' => !empty($request->tanggal_mulai_penyusutan) ? $request->tanggal_mulai_penyusutan : null,
            'masa_manfaat' => !empty($request->masa_manfaat) ? $request->masa_manfaat : 0,
            'nilai_residu' => !empty($request->nilai_residu) ? Utility::remove_commas($request->nilai_residu) : 0,
            'pilihan_nilai' => !empty($request->pilihan_nilai) ? $request->pilihan_nilai : '',
            'dpp' => !empty($request->dpp) ? Utility::remove_commas($request->dpp) : 0,
            'ppn' => !empty($request->ppn) ? Utility::remove_commas($request->ppn) : 0,
            'nilai_akum_penyusutan' => !empty($request->nilai_akum_penyusutan) ? Utility::remove_commas($request->nilai_akum_penyusutan) : 0,
            'pilihan' => !empty($request->pilihan) ? $request->pilihan : '',
            'qty' => !empty($request->qty) ? $request->qty : 0,
            'akun_selisih' => !empty($request->akun_selisih) ? $request->akun_selisih : 0,
            'is_saldo_awal' => !empty($request->is_saldo_awal) ? $request->is_saldo_awal : 0,
            'tanggal_input_aset' => !empty($request->tanggal_input_aset) ? $request->tanggal_input_aset : null,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s')
        );
        DB::beginTransaction();
        try {
            if (empty($id)) {
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $arrData['status_aset_tetap'] = StatusEnum::OPEN;
                $arrData['created_by'] = $userId;
                $arrData['reason'] = '';
                $res = $this->create($arrData);
            } else {
                $res = $this->update($arrData, $id);
            }

            if($res){
                if(!empty($id)){
                    $this->deleteAdditional($id);
                    $idAsetTetap = $id;
                } else {
                    $idAsetTetap = $res->id;
                }
                $fileUpload = new FileUploadService();
                $uploadedFiles = $request->file('files');
                if(!empty($uploadedFiles)) {
                    if (count($uploadedFiles) > 0) {
                        foreach ($uploadedFiles as $file) {
                            $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                            if ($resUpload) {
                                $arrUpload = array(
                                    'aset_tetap_id' => $idAsetTetap,
                                    'meta_key' => 'upload',
                                    'meta_value' => $resUpload
                                );
                                PurchaseOrderMeta::create($arrUpload);
                            }
                        }
                    }
                }

                DB::commit();

                $this->activityLog->log([
                    'user_id' => $userId,
                    'action' => empty($id)
                        ? 'Tambah data order pembelian aset tetap dengan nomor ' . $asetNo
                        : 'Edit data order pembelian aset tetap dengan nomor ' . $asetNo,
                    'model_type' => PurchaseOrder::class,
                    'model_id' => $idAsetTetap,
                    'old_values' => $oldData,
                    'new_values' => $this->findOne($idAsetTetap, [], [
                        'aset_tetap_coa',
                        'dari_akun_coa',
                        'akumulasi_penyusutan_coa',
                        'penyusutan_coa',
                        'downpayment'
                    ])?->toArray(),
                    'request_payload' => RequestAuditHelper::sanitize($request),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return true;
            }

            DB::rollBack();
            return false;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[AsetTetap\\Pembelian\\OrderRepo][store] ' . $e->getMessage());
            return false;
        }
    }

    public static function changeStatus($id,$status = StatusEnum::OPEN)
    {
        $res = PurchaseOrder::findOrFail($id);
        $res->status_aset_tetap = $status;
        $res->save();
    }

    public function deleteAdditional($id)
    {
        PurchaseOrderMeta::where(array('aset_tetap_id' => $id))->delete();
    }

    public function destroy(int $id, int $userId): bool
    {
        $purchaseOrder = $this->findOne($id, [], [
            'aset_tetap_coa',
            'dari_akun_coa',
            'akumulasi_penyusutan_coa',
            'penyusutan_coa',
            'downpayment'
        ]);
        if (!$purchaseOrder) {
            return false;
        }

        $oldData = $purchaseOrder->toArray();

        DB::beginTransaction();
        try {
            $this->deleteAdditional($id);
            $this->deleteByWhere(array('id' => $id));
            DB::commit();

            $noAset = $oldData['no_aset'] ?? '';
            $this->activityLog->log([
                'user_id' => $userId,
                'action' => 'Hapus data order pembelian aset tetap dengan nomor ' . $noAset,
                'model_type' => PurchaseOrder::class,
                'model_id' => $id,
                'old_values' => $oldData,
                'new_values' => null,
                'request_payload' => RequestAuditHelper::sanitize(request()),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[AsetTetap\\Pembelian\\OrderRepo][destroy] ' . $e->getMessage());
            DB::rollback();
            return false;
        }
    }

    public function delete($id)
    {
        return $this->destroy((int) $id, (int) request()->input('user_id'));
    }
}
