<?php

namespace Icso\Accounting\Repositories\Penjualan\Order;


use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Penjualan\Order\SalesOrder;
use Icso\Accounting\Models\Penjualan\Order\SalesOrderMeta;
use Icso\Accounting\Models\Penjualan\Order\SalesOrderProduct;
use Icso\Accounting\Models\Penjualan\Order\SalesQuotation;
use Icso\Accounting\Models\Penjualan\Order\SalesQuotationProduct;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDeliveryProduct;
use Icso\Accounting\Models\Penjualan\Spk\SalesSpkProduct;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesOrderRepo extends ElequentRepository
{
    protected $model;

    public function __construct(SalesOrder $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        $model = new $this->model;
        $dataSet = $model->when(!empty($where), function ($query) use($where){
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
        })->when(!empty($search), function ($query) use($search){
            $query->where('order_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhereHas('vendor', function ($query) use($search) {
                $query->where('vendor_name', 'like', '%' .$search. '%');
                $query->orWhere('vendor_company_name', 'like', '%' .$search. '%');
        });
        })->orderBy('order_date','desc')->with(['vendor','vendor.vendor_meta','orderproduct','ordermeta','orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup','orderproduct.tax.taxgroup.tax','orderproduct.unit'])->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($where), function ($query) use($where){
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
        })->when(!empty($search), function ($query) use($search){
            $query->where('order_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhereHas('vendor', function ($query) use($search) {
                $query->where('vendor_name', 'like', '%' .$search. '%');
                $query->orWhere('vendor_company_name', 'like', '%' .$search. '%');
            });
        })->orderBy('order_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $userId = $request->user_id;
        $orderNo = $request->order_no;
        if (empty($orderNo)) {
            $orderNo = self::generateCodeTransaction(new SalesOrder(), KeyNomor::NO_ORDER_PENJUALAN, 'order_no', 'order_date');
        }
        $orderDate = !empty($request->order_date) ? Utility::changeDateFormat($request->order_date) : date('Y-m-d');
        $dateSend = $request->date_send;
        $vendorId = !empty($request->vendor_id) ? $request->vendor_id : '0';
        $quotationId = !empty($request->quotation_id) ? (int) $request->quotation_id : 0;
        $subtotal = !empty($request->subtotal) ? Utility::remove_commas($request->subtotal) : 0;
        $discount = !empty($request->discount) ? Utility::remove_commas($request->discount) : 0;
        $totalDiscount = !empty($request->total_discount) ? Utility::remove_commas($request->total_discount) : 0;
        $grandtotal = !empty($request->grandtotal) ? Utility::remove_commas($request->grandtotal) : 0;
        $note = !empty($request->note) ? $request->note : '';
        $discountType = !empty($request->discount_type) ? $request->discount_type : '';
        $taxType = !empty($request->tax_type) ? $request->tax_type : '';
        $orderType = !empty($request->order_type) ? $request->order_type : '';
        $serviceType = !empty($request->service_type) ? $request->service_type : '';
        $serviceStartPeriode = !empty($request->service_start_period) ? $request->service_start_period : '';
        $serviceEndPeriode = !empty($request->service_start_period) ? $request->service_end_period : '';
        $arrData = $this->preparedDataArray($orderNo, $orderDate,$userId,$note,$dateSend,$vendorId,$subtotal,$discount,$totalDiscount,$discountType,$taxType,$grandtotal,$serviceType,$serviceStartPeriode,$serviceEndPeriode,$quotationId);
        DB::beginTransaction();
        try {
            $isNewOrder = empty($id);
            $existingOrder = null;

            if (!$isNewOrder) {
                $existingOrder = SalesOrder::with('orderproduct')->find($id);
                if (empty($existingOrder)) {
                    throw new \Exception('Sales order tidak ditemukan');
                }

                if (!empty($existingOrder->quotation_id)) {
                    $this->restoreQuotationProductQtyLeft($existingOrder->quotation_id, $existingOrder->orderproduct);
                }
            }

            if ($isNewOrder) {
                $res = $this->handleNewData($arrData,$orderType,$userId);
            } else {
                $res = $this->update($arrData, $id);
            }
            if ($res) {
                $idOrder = $isNewOrder ? $res->id : $id;
                if (!$isNewOrder) {
                    $this->deleteAdditional($id);
                }
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                if (is_array($request->orderproduct)) {
                    $products = json_decode(json_encode($request->orderproduct));
                } else {
                    $products = $request->orderproduct;
                }
                if (is_array($request->ordermeta)) {
                    $metas  = json_decode(json_encode($request->ordermeta));
                } else {
                    $metas = $request->ordermeta;
                }
                $this->handleOrderProducts($products, $idOrder, $taxType);
                if (!empty($quotationId)) {
                    $this->syncQuotationProductQtyLeft($quotationId, $products);
                }
                $this->handleMetas($metas,$idOrder);
                $this->handleFileUploads($request->file('files'), $idOrder, $userId);
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                DB::commit();
                return true;
            } else {
                return false;
            }
        }catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    private function handleMetas($metas,$idOrder)
    {
        if(!empty($metas)){
            foreach ($metas as $item) {
                if(!empty($item->meta_value)){
                    $arrDataMeta = [
                        'meta_key' => $item->meta_key,
                        'meta_value' => $item->meta_value,
                        'order_id' => $idOrder
                    ];
                    SalesOrderMeta::create($arrDataMeta);
                }

            }
        }

    }

    public function handleNewData($arrData, $orderType, $userId)
    {
        $arrData['order_status'] = StatusEnum::OPEN;
        $arrData['reason'] = "";
        $arrData['order_type'] = $orderType;
        $arrData['created_at'] = date('Y-m-d H:i:s');
        $arrData['created_by'] = $userId;
        $res = $this->create($arrData);
        return $res;
    }

    public function preparedDataArray($orderNo, $orderDate, $userId, $note, $dateSend, $vendorId, $subtotal, $discount, $totalDiscount, $discountType, $taxType, $grandTotal, $serviceType, $serviceStartPeriode, $serviceEndPeriode, $quotationId=0)
    {
        return [
            'order_no' => $orderNo,
            'order_date' => Utility::changeDateFormat($orderDate),
            'note' => $note,
            'date_send' => Utility::changeDateFormat($dateSend),
            'vendor_id' => $vendorId,
            'subtotal' => Utility::remove_commas($subtotal ?: 0),
            'discount' => Utility::remove_commas($discount ?: 0),
            'discount_type' => $discountType ?: '',
            'total_discount' => Utility::remove_commas($totalDiscount ?: 0),
            'total_tax' => 0,
            'tax_type' => $taxType ?: '',
            'total_dpp' => 0,
            'grandtotal' => Utility::remove_commas($grandTotal ?: 0),
            'service_type' => $serviceType   ?: '',
            'service_start_period' => $serviceStartPeriode ?: '',
            'service_end_period' => $serviceEndPeriode ?: '',
            'updated_by' => $userId,
            'quotation_id' => $quotationId,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    public function handleOrderProducts($orderProducts, $orderId, $taxType)
    {
        foreach ($orderProducts as $item) {
            $this->createProduct($item, $orderId, $taxType);
        }

    }

    public function createProduct($item, $orderId, $taxType='')
    {
        $arrItem = [
            'qty' => Utility::remove_commas($item->qty),
            'qty_left' => Utility::remove_commas($item->qty),
            'product_id' => $item->product_id ?: '0',
            'unit_id' => $item->unit_id ?: '0',
            'tax_id' => $item->tax_id ?: '0',
            'tax_percentage' => $item->tax_percentage ?: '0',
            'price' => Utility::remove_commas($item->price ?: 0),
            'tax_type' => $taxType ?: '',
            'discount_type' => $item->discount_type ?: '',
            'discount' => Utility::remove_commas($item->discount ?: 0),
            'subtotal' => Utility::remove_commas($item->subtotal ?: 0),
            'multi_unit' => 0,
            'order_id' => $orderId,
        ];
       $res = SalesOrderProduct::create($arrItem);
       return $res;
    }

    private function handleFileUploads($uploadedFiles, $orderId, $userId)
    {
        if (!empty($uploadedFiles)) {
            $fileUpload = new FileUploadService();
            foreach ($uploadedFiles as $file) {
                $resUpload = $fileUpload->upload($file, tenant(), $userId);
                if ($resUpload) {
                    $arrUpload = [
                        'order_id' => $orderId,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload
                    ];
                    SalesOrderMeta::create($arrUpload);
                }
            }
        }
    }

    public function deleteAdditional($idOrder){
        SalesOrderProduct::where(array('order_id' => $idOrder))->delete();
        SalesOrderMeta::where(array('order_id' => $idOrder))->delete();
    }

    public function findInUseInDeliveryOrSpkById($idOrder)
    {
        $find = $this->findOne($idOrder,array(), ['orderproduct','orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup','orderproduct.tax.taxgroup.tax','orderproduct.unit']);
        $arrDataProduk = array();
        $statusCompleted = false;
        if(!empty($find)) {
            $findProductOrder = $find->orderproduct;
            $totalCompleted = 0;
            $orderProductCount = count($findProductOrder);
            foreach ($findProductOrder as $item) {
                $product = $item->product;
                if (!empty($product)) {
                    if ($product->product_type == ProductType::ITEM) {
                        $totalKirim = SalesDeliveryProduct::where(array('order_product_id' => $item->id))->sum('qty');
                        if($totalKirim >= $item->qty){
                            $totalCompleted = $totalCompleted + 1;
                        } else {
                            $sisa = $item->qty - $totalKirim;
                            $item->qty_left = $sisa;
                            $item->qty_delivered = $totalKirim;
                            $arrDataProduk[] = $item;
                        }
                    }
                    else {
                        if ($product->product_type == ProductType::SERVICE) {
                            $totalSpk = SalesSpkProduct::where(array('order_product_id' => $item->id))->sum('qty');
                            if($totalSpk >= $item->qty){
                                $totalCompleted = $totalCompleted + 1;
                            } else {
                                $sisa = $item->qty - $totalSpk;
                                $item->qty_left = $sisa;
                                $item->qty_delivered = $totalSpk;
                                $arrDataProduk[] = $item;
                            }
                        } else {
                            $orderProductCount = $orderProductCount - 1;
                        }

                    }
                }
            }
            if($totalCompleted == $orderProductCount){
                $statusCompleted = true;
            }
        }
        return array('order_product' => $arrDataProduk, 'status_order_completed' => $statusCompleted);
    }

    public function delete($id){
        DB::beginTransaction();
        try
        {
            $order = SalesOrder::with('orderproduct')->find($id);
            if (!empty($order) && !empty($order->quotation_id)) {
                $this->restoreQuotationProductQtyLeft($order->quotation_id, $order->orderproduct);
            }
            $this->deleteAdditional($id);
            $this->deleteByWhere(array('id' => $id));
            DB::commit();
            return true;
        }
        catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }

    public static function changeStatusByDelivery($idOrder)
    {
        $orderRepo = new self(new SalesOrder());
        $find = $orderRepo->findOne($idOrder);
        $delivery = $orderRepo->findInUseInDeliveryOrSpkById($idOrder);
        if($delivery['status_order_completed']){
            $arrData = array('order_status' => StatusEnum::DELIVERY);
            $orderRepo->update($arrData,$find->id);
        } else {
            $findDelivery = SalesDelivery::where(array('order_id' => $find->id))->count();
            if($findDelivery> 0){
                $arrData = array('order_status' => StatusEnum::PARSIAL_DELIVERY);
                $orderRepo->update($arrData,$find->id);
            }
        }
    }

    public static function changeStatusOrderById($id,$statusOrder= StatusEnum::INVOICE)
    {
        $orderRepo = new self(new SalesOrder());
        $arrUpdateStatus = array(
            'order_status' => $statusOrder
        );
        $orderRepo->update($arrUpdateStatus, $id);
    }

    public static function closeStatusOrderById($id)
    {
        $orderRepo = new self(new SalesOrder());
        $find = $orderRepo->findOne($id);
        if(!empty($find)){
            if($find->order_status == StatusEnum::DELIVERY)
            {
                self::changeStatusOrderById($id);
            }
        }

    }

    private function syncQuotationProductQtyLeft($quotationId, $orderProducts)
    {
        $requiredQtyByProduct = $this->sumQtyByProduct($orderProducts);
        if (empty($requiredQtyByProduct)) {
            $this->syncQuotationStatus($quotationId);
            return;
        }

        foreach ($requiredQtyByProduct as $productId => $requiredQty) {
            $rows = SalesQuotationProduct::where('quotation_id', $quotationId)
                ->where('product_id', $productId)
                ->where('qty_left', '>', 0)
                ->orderBy('id')
                ->get();

            $availableQty = (float) $rows->sum('qty_left');
            if ($availableQty < $requiredQty) {
                throw new \Exception("Qty quotation tidak cukup untuk product_id {$productId}");
            }

            $remaining = $requiredQty;
            foreach ($rows as $row) {
                if ($remaining <= 0) {
                    break;
                }

                $currentQtyLeft = (float) $row->qty_left;
                if ($currentQtyLeft <= 0) {
                    continue;
                }

                $used = min($remaining, $currentQtyLeft);
                $row->qty_left = $currentQtyLeft - $used;
                $row->save();
                $remaining -= $used;
            }
        }

        $this->syncQuotationStatus($quotationId);
    }

    private function restoreQuotationProductQtyLeft($quotationId, $orderProducts)
    {
        $restoreQtyByProduct = $this->sumQtyByProduct($orderProducts);
        if (empty($restoreQtyByProduct)) {
            $this->syncQuotationStatus($quotationId);
            return;
        }

        foreach ($restoreQtyByProduct as $productId => $restoreQty) {
            $rows = SalesQuotationProduct::where('quotation_id', $quotationId)
                ->where('product_id', $productId)
                ->orderBy('id')
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $remaining = $restoreQty;
            foreach ($rows as $row) {
                if ($remaining <= 0) {
                    break;
                }

                $maxAdditional = (float) $row->qty - (float) $row->qty_left;
                if ($maxAdditional <= 0) {
                    continue;
                }

                $added = min($remaining, $maxAdditional);
                $row->qty_left = (float) $row->qty_left + $added;
                $row->save();
                $remaining -= $added;
            }
        }

        $this->syncQuotationStatus($quotationId);
    }

    private function sumQtyByProduct($products)
    {
        $result = [];
        if (empty($products)) {
            return $result;
        }

        foreach ($products as $item) {
            $productId = (int) $this->readField($item, 'product_id', 0);
            $qty = (float) Utility::remove_commas($this->readField($item, 'qty', 0));

            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            if (!isset($result[$productId])) {
                $result[$productId] = 0;
            }

            $result[$productId] += $qty;
        }

        return $result;
    }

    private function readField($item, $field, $default = null)
    {
        if (is_array($item)) {
            return $item[$field] ?? $default;
        }

        if (is_object($item)) {
            return $item->{$field} ?? $default;
        }

        return $default;
    }

    private function syncQuotationStatus($quotationId)
    {
        $query = SalesQuotationProduct::where('quotation_id', $quotationId);
        $hasRows = (clone $query)->exists();
        if (!$hasRows) {
            return;
        }

        $hasQtyLeft = (clone $query)->where('qty_left', '>', 0)->exists();
        $hasConsumed = (clone $query)->whereColumn('qty_left', '<', 'qty')->exists();

        $status = StatusEnum::OPEN;
        if (!$hasQtyLeft) {
            $status = StatusEnum::CLOSE;
        } else if ($hasConsumed) {
            $status = StatusEnum::PARSIAL;
        }

        SalesQuotation::where('id', $quotationId)->update([
            'quotation_status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
