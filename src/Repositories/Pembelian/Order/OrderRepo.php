<?php

namespace Icso\Accounting\Repositories\Pembelian\Order;

use Icso\Accounting\Enums\OrderTypeEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TransactionType;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Pembelian\Bast\PurchaseBastProduct;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrder;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrderMeta;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrderProduct;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceivedProduct;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Pembelian\Request\RequestRepo;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\Utility;
use Icso\Accounting\Utils\VarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderRepo extends ElequentRepository
{
    protected $model;

    public function __construct(PurchaseOrder $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('order_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('order_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('order_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('order_date','desc')->count();
        return $dataSet;
    }

    public function getAllDataBetweenBy($search, $page, $perpage, array $where = [], array $whereBetween=[])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    $que->$metod($item['value']);
                }
            });
        })->when(!empty($search), function ($query) use($search){
            $query->where('order_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhereHas('vendor', function ($query) use($search) {
                $query->where('vendor_name', 'like', '%' .$search. '%');
                $query->orWhere('vendor_company_name', 'like', '%' .$search. '%');
            });
            $query->orWhereHas('purchaserequest', function ($query) use($search) {
                $query->where('request_no', 'like', '%' .$search. '%');
                $query->orWhere('request_from', 'like', '%' .$search. '%');
            });
        })->when(!empty($whereBetween), function ($query) use($whereBetween){
            $query->whereBetween('order_date', $whereBetween);
        })->orderBy('order_date','desc')->with(['vendor','purchaserequest','coa','orderproduct','orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup','orderproduct.tax.taxgroup.tax','orderproduct.unit'])->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBetweenBy($search, array $where = [], array $whereBetween=[])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    $que->$metod($item['value']);
                }
            });
        })->when(!empty($search), function ($query) use($search){
            $query->where('order_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhereHas('vendor', function ($query) use($search) {
                $query->where('vendor_name', 'like', '%' .$search. '%');
                $query->orWhere('vendor_company_name', 'like', '%' .$search. '%');
            });
            $query->orWhereHas('purchaserequest', function ($query) use($search) {
                $query->where('request_no', 'like', '%' .$search. '%');
                $query->orWhere('request_from', 'like', '%' .$search. '%');
            });
        })->when(!empty($whereBetween), function ($query) use($whereBetween){
            $query->whereBetween('order_date', $whereBetween);
        })->orderBy('order_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        $id = $request->id;
        $userId = $request->user_id;
        $orderNo = $request->order_no ?: self::generateCodeTransaction(new PurchaseOrder(), KeyNomor::NO_ORDER_PEMBELIAN, 'order_no', 'order_date');
        $orderDate = Utility::changeDateFormat($request->order_date ?: date('Y-m-d'));
        $dateSend = $request->date_send;
        $requestId = $request->request_id ?: '0';
        $coaId = $request->coa_id ?: '0';
        $vendorId = $request->vendor_id ?: '0';
        $subtotal = Utility::remove_commas($request->subtotal ?: 0);
        $discount = Utility::remove_commas($request->discount ?: 0);
        $totalDiscount = Utility::remove_commas($request->total_discount ?: 0);
        $totalTax = Utility::remove_commas($request->total_tax ?: 0);
        $totalDpp = Utility::remove_commas($request->total_dpp ?: 0);
        $grandtotal = Utility::remove_commas($request->grandtotal ?: 0);
        $note = $request->note ?: '';
        $discountType = $request->discount_type ?: '';
        $taxType = $request->tax_type ?: '';
        $orderType = $request->order_type ?: '';

        $arrData = $this->prepareDataArray($orderNo,$orderDate,$note,$dateSend,$requestId,$coaId,$vendorId,$subtotal,$discount,$discountType,$totalDiscount,$totalTax,$taxType,$totalDpp,$grandtotal,$orderType,$userId);

        DB::beginTransaction();
        try {
            if (empty($id)) {
               $res = $this->handleNewOrder($arrData, $userId,$orderType);
            } else {
                $this->handleExistingOrder($arrData, $id, $requestId);
            }

            $this->processProducts( json_decode(json_encode($request->orderproduct)), $id ?? $res->id);
            $this->handleFileUploads($request->file('files'), $userId, $id ?? $res->id);
            if(!empty($request->request_id)){
                RequestRepo::changeStatusRequest($request->request_id);
            }
            DB::commit();
            return true;
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function prepareDataArray($orderNo,$orderDate,$note,$dateSend,$requestId,$coaId,$vendorId,$subtotal,$discount,$discountType,$totalDiscount,$totalTax,$taxType,$totalDpp,$grandtotal,$orderType,$userId)
    {
        $arrData = [
            'order_no' => $orderNo,
            'order_date' => $orderDate,
            'note' => $note,
            'date_send' => Utility::changeDateFormat($dateSend),
            'request_id' => $requestId,
            'coa_id' => $coaId,
            'vendor_id' => $vendorId,
            'subtotal' => $subtotal,
            'discount' => !empty($discount) ? $discount : 0,
            'discount_type' => !empty($discountType) ? $discountType : "fix",
            'total_discount' => $totalDiscount,
            'total_tax' => $totalTax,
            'tax_type' => $taxType,
            'total_dpp' => $totalDpp,
            'grandtotal' => $grandtotal,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $arrData;
    }

    public function handleNewOrder(array $arrData, $userId, $orderType= ProductType::ITEM)
    {
        $arrData['order_status'] = StatusEnum::OPEN;
        $arrData['order_type'] = $orderType;
        $arrData['reason'] = "";
        $arrData['created_at'] = date('Y-m-d H:i:s');
        $arrData['created_by'] = $userId;
        return $this->create($arrData);
    }

    private function handleExistingOrder(array $arrData, $id, $requestId)
    {
        $orderIdOld = $this->findOne($id);
        $res = $this->update($arrData, $id);

        if ($res) {
            $this->deleteAdditional($id);
            $this->updateRequestStatus($orderIdOld->request_id, $requestId);
        }
    }

    public function processProducts($products, $orderId)
    {
        if (count($products) > 0) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            foreach ($products as $item) {
                $this->createProduct($item, $orderId);
            }
           DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function createProduct($item, $orderId)
    {
        $arrItem = [
            'qty' => $item->qty,
            'product_id' => $item->product_id ?: '0',
            'service_name' => $item->service_name ?: '',
            'unit_id' => $item->unit_id ?: '0',
            'tax_id' => $item->tax_id ?: '0',
            'tax_percentage' => $item->tax_percentage ?: '0',
            'price' => Utility::remove_commas($item->price ?: 0),
            'tax_type' => $item->tax_type ?: '',
            'discount_type' => $item->discount_type ?: '',
            'discount' => Utility::remove_commas($item->discount ?: 0),
            'subtotal' => Utility::remove_commas($item->subtotal ?: 0),
            'request_product_id' => $item->request_product_id ?: 0,
            'multi_unit' => 0,
            'tax_group' => Helpers::getJsonTaxGroup($item->tax_id),
            'order_id' => $orderId,
        ];
        PurchaseOrderProduct::create($arrItem);
    }

    private function handleFileUploads($uploadedFiles, $userId, $orderId)
    {
        if ($uploadedFiles && count($uploadedFiles) > 0) {
            $fileUpload = new FileUploadService();
            foreach ($uploadedFiles as $file) {
                $this->uploadFile($fileUpload, $file, $userId, $orderId);
            }
        }
    }

    private function uploadFile($fileUpload, $file, $userId, $orderId)
    {
        $resUpload = $fileUpload->upload($file, tenant(), $userId);
        if ($resUpload) {
            $arrUpload = [
                'order_id' => $orderId,
                'meta_key' => 'upload',
                'meta_value' => $resUpload
            ];
            PurchaseOrderMeta::create($arrUpload);
        }
    }

    private function updateRequestStatus($oldRequestId, $newRequestId)
    {
        if (!empty($oldRequestId) && $oldRequestId != $newRequestId) {
            RequestRepo::changeStatusRequest($oldRequestId);
        }
        if (!empty($newRequestId)) {
            RequestRepo::changeStatusRequest($newRequestId);
        }
    }

    public function deleteAdditional($idOrder){
        PurchaseOrderProduct::where(array('order_id' => $idOrder))->delete();
        PurchaseOrderMeta::where(array('order_id' => $idOrder))->delete();

    }

    public function delete($id){
        DB::beginTransaction();
        try
        {
            $find = $this->findOne($id);
            $idReq = $find->request_id;
            $this->deleteAdditional($id);
            $this->deleteByWhere(array('id' => $id));
            if(!empty($idReq)){
                RequestRepo::changeStatusRequest($idReq);
            }
            DB::commit();
            return true;
        }
        catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollback();
            return false;
        }
    }

    public function findInUseInPenerimaanById($idOrder){
        $find = $this->findOne($idOrder,array(),['orderproduct','orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup','orderproduct.tax.taxgroup.tax','orderproduct.unit']);
        $arrDataProduk = array();
        $arrReceivedProduk = array();
        $statusCompleted = false;
        if(!empty($find)){
            $findProductOrder = $find->orderproduct;
            $totalCompleted = 0;
            $orderProductCount = count($findProductOrder);
            foreach ($findProductOrder as $item){
                $product = $item->product;
                if(!empty($product)){
                    if($product->product_type == ProductType::ITEM){
                        $totalTerima = PurchaseReceivedProduct::where(array('order_product_id' => $item->id))->sum('qty');
                        if($totalTerima >= $item->qty){
                            $totalCompleted = $totalCompleted + 1;
                        } else {
                            $sisa = $item->qty - $totalTerima;
                            $item->qty_left = $sisa;
                            $item->qty_received = $totalTerima;
                            $arrDataProduk[] = $item;
                        }
                    } else {
                        $orderProductCount = $orderProductCount - 1;
                    }
                }


            }
            if($totalCompleted == $orderProductCount){
                $statusCompleted = true;
            }
        }
        return array('order_product' => $arrDataProduk, 'status_order_completed' => $statusCompleted);
    }

    public static function changeStatusPenerimaan($idOrder)
    {
        $orderRepo = new self(new PurchaseOrder());
        $find = $orderRepo->findOne($idOrder);
        $terima = $orderRepo->findInUseInPenerimaanById($find->id);
        if($terima['status_order_completed']){
            $arrData = array('order_status' => StatusEnum::PENERIMAAN);
            $orderRepo->update($arrData,$find->id);
        } else {
            $findTerima = PurchaseReceived::where(array('order_id' => $find->id))->count();
            if($findTerima> 0){
                $arrData = array('order_status' => StatusEnum::PARSIAL_PENERIMAAN);
                $orderRepo->update($arrData,$find->id);
            }
        }
    }

    public function getTransaksi($idOrder): array
    {
        $arrTransaksi = array();
        $item = $this->findOne($idOrder,array(),['downpayment','received','received.retur','invoice','invoice.payment.purchasepayment']);
        if(!empty($item)){
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
        return $arrTransaksi;
    }

    public static function changeStatusOrderById($id,$statusOrder= StatusEnum::INVOICE)
    {
        $orderRepo = new self(new PurchaseOrder());
        $arrUpdateStatus = array(
            'order_status' => $statusOrder
        );
        $orderRepo->update($arrUpdateStatus, $id);
    }

    public static function closeStatusOrderById($id)
    {
        $orderRepo = new self(new PurchaseOrder());
        $find = $orderRepo->findOne($id);
        if(!empty($find)){
            if($find->order_status == StatusEnum::PENERIMAAN)
            {
                self::changeStatusOrderById($id);
            }
        }

    }

    public function findInUseInBastById($idOrder){
        $find = $this->findOne($idOrder,array(),['orderproduct']);
        $arrDataProduk = array();
        $statusCompleted = false;
        if(!empty($find)){
            $findProductOrder = $find->orderproduct;
            $totalCompleted = 0;
            $orderProductCount = count($findProductOrder);
            foreach ($findProductOrder as $item){
                $totalTerima = PurchaseBastProduct::where(array('order_product_id' => $item->id))->sum('qty');
                if($totalTerima >= $item->qty){
                    $totalCompleted = $totalCompleted + 1;
                } else {
                    $sisa = $item->qty - $totalTerima;
                    $item->qty_left = $sisa;
                    $item->qty_bast = $totalTerima;
                    $arrDataProduk[] = $item;
                }
            }
            if($totalCompleted == $orderProductCount){
                $statusCompleted = true;
            }
        }
        return array('order_service' => $arrDataProduk, 'status_order_completed' => $statusCompleted);
    }
}
