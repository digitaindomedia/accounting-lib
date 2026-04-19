<?php

namespace Icso\Accounting\Repositories\Penjualan\Spk;

use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Penjualan\Spk\SalesSpk;
use Icso\Accounting\Models\Penjualan\Spk\SalesSpkMeta;
use Icso\Accounting\Models\Penjualan\Spk\SalesSpkProduct;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Services\ActivityLogService;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\RequestAuditHelper;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpkRepo extends ElequentRepository
{
    protected $model;
    protected ActivityLogService $activityLog;

    public function __construct(SalesSpk $model, ActivityLogService $activityLog)
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
            $query->where(function ($que) use($search){
                $que->where('spk_no', 'like', '%' .$search. '%');
                $que->orWhereHas('order', function ($qOrder) use ($search) {
                    $qOrder->where('order_no', 'like', '%' .$search. '%');
                });
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
        })->with(['vendor','order','spkproduct','spkproduct.product','spkproduct.product.unit'])->orderBy('spk_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = []): int
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where(function ($que) use($search){
                $que->where('spk_no', 'like', '%' .$search. '%');
                $que->orWhereHas('order', function ($qOrder) use ($search) {
                    $qOrder->where('order_no', 'like', '%' .$search. '%');
                });
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
        })->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = []): bool
    {
        $id = $request->id;
        $userId = $request->user_id;
        $oldData = null;
        if (!empty($id)) {
            $oldData = $this->findOne($id,array(),['vendor','order','spkproduct','spkproduct.orderproduct','spkproduct.product'])?->toArray();
        }
        $spkNo = $request->spk_no;
        if(empty($spkNo)){
            $spkNo = self::generateCodeTransaction(new SalesSpk(),KeyNomor::NO_SPK,'spk_no','spk_date');
        }
        $spkDate = !empty($request->spk_date) ? Utility::changeDateFormat($request->spk_date) : date('Y-m-d');
        $orderId = $request->order_id;
        $vendorId = $request->vendor_id;
        $note = $request->note;
        $arrData = array(
            'spk_no' => $spkNo,
            'spk_date' => $spkDate,
            'order_id' => $orderId,
            'vendor_id' => $vendorId,
            'note' => $note,
            'updated_by' => $userId,
            'updated_at' => now()
        );
        
        DB::beginTransaction();
        try {
            if(empty($id)){
                $arrData['reason'] = '';
                $arrData['spk_status'] = StatusEnum::SELESAI;
                $arrData['created_at'] = now();
                $arrData['created_by'] = $userId;
                $res = $this->create($arrData);
                $action = 'Tambah data SPK penjualan dengan nomor ' . $spkNo;
            } else{
                $res = $this->update($arrData, $id);
                $action = 'Edit data SPK penjualan dengan nomor ' . $spkNo;
            }
            if($res){
                if(!empty($id)){
                    $this->deleteAdditional($id);
                    $idSpk = $id;
                } else {
                    $idSpk = $res->id;
                }
                $products = json_decode(json_encode($request->spkproduct));
                if (count($products) > 0) {
                    foreach ($products as $item) {
                        $arrItem = array(
                            'spk_id' => $idSpk,
                            'qty' => $item->qty,
                            'product_id' => $item->product_id,
                            'order_product_id' => $item->order_product_id
                        );
                        SalesSpkProduct::create($arrItem);
                    }
                }
                $fileUpload = new FileUploadService();
                $uploadedFiles = $request->file('files');
                if(!empty($uploadedFiles)) {
                    if (count($uploadedFiles) > 0) {
                        foreach ($uploadedFiles as $file) {
                            // Handle each file as needed
                            $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                            if ($resUpload) {
                                $arrUpload = array(
                                    'spk_id' => $idSpk,
                                    'meta_key' => 'upload',
                                    'meta_value' => $resUpload
                                );
                                SalesSpkMeta::create($arrUpload);
                            }
                        }
                    }
                }
                DB::commit();

                $this->activityLog->log([
                    'user_id' => $userId,
                    'action' => $action,
                    'model_type' => SalesSpk::class,
                    'model_id' => $idSpk,
                    'old_values' => $oldData,
                    'new_values' => $this->findOne($idSpk,array(),['vendor','order','spkproduct','spkproduct.orderproduct','spkproduct.product'])?->toArray(),
                    'request_payload' => RequestAuditHelper::sanitize($request),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return true;
            }
            else {
                DB::rollBack();
                return false;
            }
        }
        catch (\Exception $e){
            Log::error('[SpkRepo][store] ' . $e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function deleteAdditional($id)
    {
        SalesSpkProduct::where(array('spk_id' =>  $id))->delete();
        SalesSpkMeta::where(array('spk_id' =>  $id))->delete();

    }

    public function getSpkProduct($spkId,$idProduct)
    {
        $totalKirim = SalesSpkProduct::where(array('product_id' => $idProduct, 'spk_id' => $spkId))->sum('qty');
        return $totalKirim;
    }

    public function destroy(int $id, int $userId): bool
    {
        $spk = $this->findOne($id,array(),['vendor','order','spkproduct','spkproduct.orderproduct','spkproduct.product']);
        if (!$spk) {
            return false;
        }

        $oldData = $spk->toArray();

        DB::beginTransaction();
        try {
            $this->deleteAdditional($id);
            parent::delete($id);
            DB::commit();

            $spkNo = $oldData['spk_no'] ?? '';
            $this->activityLog->log([
                'user_id' => $userId,
                'action' => 'Hapus data SPK penjualan dengan nomor ' . $spkNo,
                'model_type' => SalesSpk::class,
                'model_id' => $id,
                'old_values' => $oldData,
                'new_values' => null,
                'request_payload' => RequestAuditHelper::sanitize(request()),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[SpkRepo][destroy] ' . $e->getMessage());
            return false;
        }
    }
}
