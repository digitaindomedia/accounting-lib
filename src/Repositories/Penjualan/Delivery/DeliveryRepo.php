<?php

namespace Icso\Accounting\Repositories\Penjualan\Delivery;


use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Penjualan\Order\SalesOrderProduct;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDeliveryMeta;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDeliveryProduct;
use Icso\Accounting\Models\Penjualan\Retur\SalesReturProduct;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Penjualan\Order\SalesOrderRepo;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\TransactionsCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryRepo extends ElequentRepository
{
    protected $model;

    public function __construct(SalesDelivery $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
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
            $query->where('delivery_no', 'like', '%' .$search. '%');
            $query->orWhereHas('vendor', function ($query) use($search) {
                $query->where('vendor_name', 'like', '%' .$search. '%');
                $query->orWhere('vendor_company_name', 'like', '%' .$search. '%');
            });
            $query->orWhereHas('order', function ($query) use($search) {
                $query->where('order_no', 'like', '%' .$search. '%');
            });
        })->orderBy('delivery_date','desc')->with(['vendor','order','warehouse','deliveryproduct','deliveryproduct.product','deliveryproduct.unit','deliveryproduct.tax'])->offset($page)->limit($perpage)->get();
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
            $query->where('delivery_no', 'like', '%' .$search. '%');
            $query->orWhereHas('vendor', function ($query) use($search) {
                $query->where('vendor_name', 'like', '%' .$search. '%');
                $query->orWhere('vendor_company_name', 'like', '%' .$search. '%');
            });
            $query->orWhereHas('order', function ($query) use($search) {
                $query->where('order_no', 'like', '%' .$search. '%');
            });
        })->orderBy('delivery_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $userId = $request->user_id;
        $deliveryNo = $request->delivery_no;
        if (empty($deliveryNo)) {
            $deliveryNo = self::generateCodeTransaction(new SalesDelivery(), KeyNomor::NO_DELIVERY_ORDER, 'delivery_no', 'delivery_date');
        }
        $deliveryDate = $request->delivery_date;
        $orderId = $request->order_id;
        $vendorId = $request->vendor_id;
        $warehouseId = $request->warehouse_id;
        $note = $request->note;

        $arrData = array(
            'delivery_no' => $deliveryNo,
            'delivery_date' => $deliveryDate,
            'order_id' => $orderId,
            'vendor_id' => $vendorId,
            'warehouse_id' => $warehouseId,
            'note' => $note,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
        );
        DB::beginTransaction();
        try {
            if (empty($id)) {
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $arrData['created_by'] = $userId;
                $arrData['delivery_status'] = StatusEnum::OPEN;
                $res = $this->create($arrData);
            } else {
                $res = $this->update($arrData, $id);
            }
            if ($res) {
                if (!empty($id)) {
                    $this->deleteAdditional($id);
                    $deliveryId = $id;
                } else {
                    $deliveryId = $res->id;
                }
                $products = json_decode(json_encode($request->deliveryproduct));
                if (count($products) > 0) {
                    foreach ($products as $item) {
                        $findProductOrder = SalesOrderProduct::where(array('id' => $item->order_product_id))->first();
                        if(!empty($findProductOrder)){
                            $sellPrice = $findProductOrder->price;
                            $taxId = $findProductOrder->tax_id;
                            $taxPercentage = $findProductOrder->tax_percentage;
                            $subtotal = Helpers::hitungSubtotal($item->qty,$sellPrice,$findProductOrder->discount,$findProductOrder->discount_type);
                            $arrItem = array(
                                'delivery_id' => $deliveryId,
                                'qty' => $item->qty,
                                'qty_left' => $item->qty,
                                'product_id' => $item->product_id,
                                'unit_id' => $item->unit_id,
                                'order_product_id' => $item->order_product_id,
                                'multi_unit' => '0',
                                'hpp_price' => 0,
                                'sell_price' => $sellPrice,
                                'tax_id' =>$taxId,
                                'tax_percentage' => $taxPercentage,
                                'discount' => $findProductOrder->discount,
                                'subtotal' => $subtotal,
                                'tax_type' => $findProductOrder->tax_type,
                                'discount_type' => $findProductOrder->discount_type,
                            );
                            SalesDeliveryProduct::create($arrItem);
                        }

                    }
                }
                $this->postingJurnal($deliveryId);
                SalesOrderRepo::changeStatusByDelivery($orderId);
                $fileUpload = new FileUploadService();
                $uploadedFiles = $request->file('files');
                if(!empty($uploadedFiles)) {
                    if (count($uploadedFiles) > 0) {
                        foreach ($uploadedFiles as $file) {
                            // Handle each file as needed
                            $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                            if ($resUpload) {
                                $arrUpload = array(
                                    'delivery_id' => $deliveryId,
                                    'meta_key' => 'upload',
                                    'meta_value' => $resUpload
                                );
                                SalesDeliveryMeta::create($arrUpload);
                            }
                        }
                    }
                }
                DB::commit();
                return true;
            }
            else {
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
        InventoryRepo::deleteInventory(TransactionsCode::DELIVERY_ORDER, $id);
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::DELIVERY_ORDER,$id);
        SalesDeliveryProduct::where(array('delivery_id' => $id))->delete();
        SalesDeliveryMeta::where(array('delivery_id' => $id))->delete();
    }

    public function postingJurnal($id)
    {
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $inventoryRepo = new InventoryRepo(new Inventory());
        $coaSediaanDalamPerjalanan = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN_DALAM_PERJALANAN);
        $coaSediaan = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN);
        $find = $this->findOne($id,array(),['deliveryproduct','deliveryproduct.product']);
        if(!empty($find)){
            $deliveryDate = $find->delivery_date;
            $deliveryNo = $find->delivery_no;
            if(!empty($find->deliveryproduct)) {
                $deliveryProduct = $find->deliveryproduct;
                $totalAllSediaan = 0;
                if (count($deliveryProduct) > 0) {
                    foreach ($deliveryProduct as $item) {
                        $product = $item->product;
                        $productName = "";
                        if(!empty($product)){
                            if(!empty($product->coa_id)){
                                $coaSediaan = $product->coa_id;

                            }
                            $productName = $product->item_name;
                        }
                        $noteProduct = !empty($productName) ? " dengan nama ".$productName : "";
                        $hpp = $inventoryRepo->movingAverageByDate($item->product_id, $item->unit_id, $deliveryDate);
                        $subtotalHpp = $hpp * $item->qty;
                        $reqInventory = new Request();
                        $reqInventory->coa_id = $coaSediaan;
                        $reqInventory->user_id = $find->created_by;
                        $reqInventory->inventory_date = $deliveryDate;
                        $reqInventory->transaction_code = TransactionsCode::DELIVERY_ORDER;
                        $reqInventory->transaction_id = $find->id;
                        $reqInventory->transaction_sub_id = $item->id;
                        $reqInventory->qty_out = $item->qty;
                        $reqInventory->warehouse_id = $find->warehouse_id;
                        $reqInventory->product_id = $item->product_id;
                        $reqInventory->price = $hpp;
                        $reqInventory->note = $find->note;
                        $reqInventory->unit_id = $item->unit_id;
                        $inventoryRepo->store($reqInventory);
                        if(!empty($subtotalHpp)){
                            $arrJurnalKredit = array(
                                'transaction_date' => $deliveryDate,
                                'transaction_datetime' => $deliveryDate." ".date('H:i:s'),
                                'created_by' => $find->created_by,
                                'updated_by' => $find->created_by,
                                'transaction_code' => TransactionsCode::DELIVERY_ORDER,
                                'coa_id' => $coaSediaan,
                                'transaction_id' => $find->id,
                                'transaction_sub_id' => $item->id,
                                'created_at' => date("Y-m-d H:i:s"),
                                'updated_at' => date("Y-m-d H:i:s"),
                                'transaction_no' => $deliveryNo,
                                'transaction_status' => JurnalStatusEnum::OK,
                                'debet' => 0,
                                'kredit' => $subtotalHpp,
                                'note' => !empty($find->note) ? $find->note : 'Pengiriman Barang'.$noteProduct,
                            );
                            $jurnalTransaksiRepo->create($arrJurnalKredit);
                        }

                        $totalAllSediaan = $totalAllSediaan + $subtotalHpp;
                    }
                }
                if(!empty($totalAllSediaan)){
                    $arrJurnalDebet = array(
                        'transaction_date' => $deliveryDate,
                        'transaction_datetime' => $deliveryDate." ".date('H:i:s'),
                        'created_by' => $find->created_by,
                        'updated_by' => $find->created_by,
                        'transaction_code' => TransactionsCode::DELIVERY_ORDER,
                        'coa_id' => $coaSediaanDalamPerjalanan,
                        'transaction_id' => $find->id,
                        'transaction_sub_id' => $item->id,
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                        'transaction_no' => $deliveryNo,
                        'transaction_status' => JurnalStatusEnum::OK,
                        'debet' => $totalAllSediaan,
                        'kredit' => 0,
                        'note' => !empty($find->note) ? $find->note : 'Pengiriman Barang'.$noteProduct,
                    );
                    $jurnalTransaksiRepo->create($arrJurnalDebet);
                }
            }
        }
    }

    public function getDeliveredProduct($deliveryId,$idProduct,$idUnit)
    {
        $totalKirim = SalesDeliveryProduct::where(array('product_id' => $idProduct, 'unit_id' => $idUnit, 'delivery_id' => $deliveryId))->sum('qty');
        return $totalKirim;
    }

    public static function getValueSediaanDalamPerjalan($idDelivery, $coaSediaanDalamPerjalananId)
    {
        $find = JurnalTransaksi::where(array('transaction_code' => TransactionsCode::DELIVERY_ORDER,'coa_id' => $coaSediaanDalamPerjalananId, 'transaction_id' => $idDelivery))->first();
        if(!empty($find)){
            return $find->debet;
        } else {
            $total = 0;
            $findInventory = Inventory::where(array('transaction_code' => TransactionsCode::DELIVERY_ORDER, 'transaction_id' => $idDelivery))->get();
            if(count($findInventory) > 0){
                foreach ($findInventory as $item){
                    $total = $total + $item->total_out;
                }
            }
            return  $total;
        }
    }

    public function getTotalDelivery($id)
    {
        $find = $this->findOne($id,array(),['deliveryproduct','deliveryproduct.orderproduct']);
        $total = 0;
        if(!empty($find)){
            $receiveProduct = $find->deliveryproduct;
            if(!empty($receiveProduct)){
                foreach ($receiveProduct as $key => $item){
                    //$orderProduct = $item->orderproduct;
                    $price = $item->sell_price;
                    $discount = 0;
                    $subtotal = $item->qty * $price;
                    if(!empty($item->discount)){
                        if(!empty($item->discount_type))
                        {
                            if($item->discount_type == TypeEnum::DISCOUNT_TYPE_PERCENT){
                                $discount = ($item->discount / 100) * $subtotal;
                            } else {
                                $discount = $item->discount;
                            }
                        }
                    }
                    $subtotal = $subtotal - $discount;
                    $total = $total + $subtotal;
                }
            }
        }
        return $total;
    }

    public function getQtyRetur($delProductId)
    {
        $qty = SalesReturProduct::where(array('delivery_product_id' => $delProductId))->sum('qty');
        return $qty;
    }

    public static function changeStatusDelivery($idDelivery, $statusDelivery=StatusEnum::SELESAI)
    {
        $instance = (new self(new SalesDelivery()));
        $arrUpdateStatus = array(
            'delivery_status' => $statusDelivery
        );
        $instance->update($arrUpdateStatus, $idDelivery);
    }


}
