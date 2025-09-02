<?php

namespace Icso\Accounting\Repositories\Pembelian\Request;


use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TransactionType;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrder;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrderProduct;
use Icso\Accounting\Models\Pembelian\Permintaan\PurchaseRequest;
use Icso\Accounting\Models\Pembelian\Permintaan\PurchaseRequestMeta;
use Icso\Accounting\Models\Pembelian\Permintaan\PurchaseRequestProduct;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\Utility;
use Icso\Accounting\Utils\VarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequestRepo extends ElequentRepository
{
    protected $model;

    public function __construct(PurchaseRequest $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('request_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhere('request_from', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('request_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('request_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhere('request_from', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('request_date','desc')->count();
        return $dataSet;
    }

    public function getAllDataBetweenBy($search, $page, $perpage, array $where = [], array $whereBetween=[])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('request_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhere('request_from', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    if($metod == 'whereBetween'){
                        $que->$metod($item['value']['field'],$item['value']['value']);
                    }
                    else {
                        $que->$metod($item['value']);
                    }
                }
            });
        })->when(!empty($whereBetween), function ($query) use($whereBetween){
            $query->whereBetween('request_date', $whereBetween);
        })->orderBy('request_date','desc')->with(['requestproduct','requestproduct.product','requestproduct.unit'])->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBetweenBy($search, array $where = [], array $whereBetween=[])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('request_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhere('request_from', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    if($metod == 'whereBetween'){
                        $que->$metod($item['value']['field'],$item['value']['value']);
                    }
                    else {
                        $que->$metod($item['value']);
                    }
                }
            });
        })->when(!empty($whereBetween), function ($query) use($whereBetween){
            $query->whereBetween('request_date', $whereBetween);
        })->orderBy('request_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = []): bool
    {
        // Extract request data
        $id = $request->id;
        $userId = $request->user_id;
        $requestNo = $this->getRequestNo($request);
        $requestDate = $this->getRequestDate($request->request_date);
        $note = $request->note ?? '';
        $requestFrom = $request->request_from ?? '';
        $requestNeedDate = $this->getRequestNeedDate($request->req_needed_date);

        // Prepare data array
        $arrData = $this->prepareDataArray($requestNo, $requestDate, $note, $requestFrom, $requestNeedDate, $request->urgency, $userId);

        DB::beginTransaction();
        try {
            $res = $this->saveRequest($arrData, $id, $userId);
            if ($res) {
                $idRequest = $this->processRequest($request, $id, $res);
                $this->processFiles($request, $idRequest);
                DB::commit();
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    private function getRequestNo($request)
    {
        return empty($request->request_no)
            ? self::generateCodeTransaction(new PurchaseRequest(), KeyNomor::NO_PERMINTAAN_PEMBELIAN, 'request_no', 'request_date')
            : $request->request_no;
    }

    private function getRequestDate($requestDate)
    {
        return !empty($requestDate) ? Utility::changeDateFormat($requestDate) : date("Y-m-d");
    }

    private function getRequestNeedDate($reqNeedDate)
    {
        return !empty($reqNeedDate) ? Utility::changeDateFormat($reqNeedDate) : date('Y-m-d');
    }

    public function prepareDataArray($requestNo, $requestDate, $note, $requestFrom, $requestNeedDate, $urgency, $userId)
    {
        return [
            'request_no' => $requestNo,
            'request_date' => $requestDate,
            'note' => $note,
            'request_from' => $requestFrom,
            'req_needed_date' => $requestNeedDate,
            'urgency' => $urgency,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    public function saveRequest($arrData, $id, $userId)
    {
        if (empty($id)) {
            $arrData['request_status'] = StatusEnum::OPEN;
            $arrData['reason'] = "";
            $arrData['created_at'] = date('Y-m-d H:i:s');
            $arrData['created_by'] = $userId;
            return $this->create($arrData);
        } else {
            return $this->update($arrData, $id);
        }
    }

    private function processRequest($request, $id, $res)
    {
        $idRequest = empty($id) ? $res->id : $id;
        if (!empty($id)) {
            $this->deleteAdditional($id);
        }
        $products = json_decode(json_encode($request->requestproduct));
        $this->saveProducts($products, $idRequest);
        return $idRequest;
    }

    public function saveProducts($products, $idRequest)
    {
        //$products = json_decode(json_encode($products));
        foreach ($products as $item) {
            $arrItem = [
                'qty' => Utility::remove_commas($item->qty),
                'qty_left' => Utility::remove_commas($item->qty),
                'product_id' => $item->product_id,
                'unit_id' => $item->unit_id,
                'note' => $item->note,
                'multi_unit' => 0,
                'request_id' => $idRequest,
            ];
            PurchaseRequestProduct::create($arrItem);
        }
    }

    private function processFiles($request, $idRequest)
    {
        $fileUpload = new FileUploadService();
        $uploadedFiles = $request->file('files');
        if (!empty($uploadedFiles)) {
            foreach ($uploadedFiles as $file) {
                $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                if ($resUpload) {
                    $arrUpload = [
                        'request_id' => $idRequest,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload
                    ];
                    PurchaseRequestMeta::create($arrUpload);
                }
            }
        }
    }

    public function deleteAdditional($idRequest): void
    {
        PurchaseRequestProduct::where(array('request_id' => $idRequest))->delete();
        PurchaseRequestMeta::where(array('request_id' => $idRequest))->delete();
    }

    public function delete($id): bool
    {
        DB::beginTransaction();
        try
        {
            $this->deleteAdditional($id);
            $this->deleteByWhere(array('id' => $id));
            DB::commit();
            return true;
        }
        catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollback();
            return false;
        }
    }

    public function getTransaksi($idPermintaan): array
    {
        $arrTransaksi = array();
        $findOrder = PurchaseOrder::where(array('request_id' => $idPermintaan))->with(['downpayment','received','received.retur','invoice','invoice.payment.purchasepayment'])->orderBy('order_date','asc')->get();
        if(count($findOrder) > 0){
            foreach ($findOrder as $item){
                $arrTransaksi[] = array(
                    VarType::TRANSACTION_DATE => $item->order_date,
                    VarType::TRANSACTION_TYPE => TransactionType::PURCHASE_ORDER,
                    VarType::TRANSACTION_NO => $item->order_no,
                    VarType::TRANSACTION_ID => $item->id
                );
                if(!empty($item->downpayment)){
                    foreach ($item->downpayment as $dp){
                        $arrTransaksi[] = array(
                            VarType::TRANSACTION_DATE => $dp->downpayment_date,
                            VarType::TRANSACTION_TYPE => TransactionType::PURCHASE_DP,
                            VarType::TRANSACTION_NO => $dp->ref_no,
                            VarType::TRANSACTION_ID => $dp->id
                        );
                    }
                }
                if(!empty($item->received)){
                    foreach ($item->received as $val){
                        $arrTransaksi[] = array(
                            VarType::TRANSACTION_DATE => $val->receive_date,
                            VarType::TRANSACTION_TYPE => TransactionType::PURCHASE_RECEIVE,
                            VarType::TRANSACTION_NO => $val->receive_no,
                            VarType::TRANSACTION_ID => $val->id
                        );
                    }
                }
                if(!empty($item->invoice)){
                    foreach ($item->invoice as $val){
                        $arrTransaksi[] = array(
                            VarType::TRANSACTION_DATE => $val->invoice_date,
                            VarType::TRANSACTION_TYPE => TransactionType::PURCHASE_INVOICE,
                            VarType::TRANSACTION_NO => $val->invoice_no,
                            VarType::TRANSACTION_ID => $val->id
                        );
                    }
                }
                if(!empty($item->invoice->payment)){
                    foreach ($item->invoice->payment as $val){
                        $pay = $val->purchasepayment;
                        if(!empty($pay)){
                            $arrTransaksi[] = array(
                                VarType::TRANSACTION_DATE => $pay->payment_date,
                                VarType::TRANSACTION_TYPE => TransactionType::PURCHASE_PAYMENT,
                                VarType::TRANSACTION_NO => $pay->payment_no,
                                VarType::TRANSACTION_ID => $pay->id
                            );
                        }

                    }
                }
                if(!empty($item->received->retur)){
                    foreach ($item->received->retur as $val){
                        $arrTransaksi[] = array(
                            VarType::TRANSACTION_DATE => $val->retur_date,
                            VarType::TRANSACTION_TYPE => TransactionType::PURCHASE_RETUR,
                            VarType::TRANSACTION_NO => $val->retur_no,
                            VarType::TRANSACTION_ID => $val->id
                        );
                    }
                }
            }
        }
        return $arrTransaksi;
    }

    public function findInUseInOrder($id)
    {
        $find = $this->findOne($id,array(),['requestproduct','requestproduct.product','requestproduct.unit']);
        $arrDataProduk = array();
        $statusCompleted = false;
        if(!empty($find)){
            $findProductRequest = $find->requestproduct;
            $totalCompleted = 0;
            $requestProductCount = count($findProductRequest);
            foreach ($findProductRequest as $item){
                $product = $item->product;
                if($product->product_type == ProductType::ITEM){
                    $totalOrder = PurchaseOrderProduct::where(array('request_product_id' => $item->id))->sum('qty');
                    if($totalOrder >= $item->qty){
                        $totalCompleted = $totalCompleted + 1;
                    } else {
                        $sisa = $item->qty - $totalOrder;
                        $item->qty = $sisa;
                        $item->qty_left = $sisa;
                        $item->qty_order = $totalOrder;
                        $arrDataProduk[] = $item;
                    }
                } else {
                    $requestProductCount = $requestProductCount - 1;
                }

            }
            if($totalCompleted == $requestProductCount){
                $statusCompleted = true;
            }
        }
        return array('request_product' => $arrDataProduk, 'status_request_completed' => $statusCompleted);
    }

    public static function changeStatusRequest($id)
    {
        $reqRepo = new self(new PurchaseRequest());
        $find = $reqRepo->findOne($id);
        if(!empty($find)){
            $order = $reqRepo->findInUseInOrder($find->id);
            if($order['status_request_completed']){
                $arrData = array('request_status' => StatusEnum::SELESAI);
                $reqRepo->update($arrData,$find->id);
            } else {
                $countOrder = PurchaseOrder::where(array('request_id' => $find->id))->count();
                if($countOrder > 0){
                    $arrData = array('request_status' => StatusEnum::PARSIAL_ORDER);
                    $reqRepo->update($arrData,$find->id);
                } else{
                    $arrData = array('request_status' => StatusEnum::OPEN);
                    $reqRepo->update($arrData,$find->id);
                }
            }
        }
    }
}
