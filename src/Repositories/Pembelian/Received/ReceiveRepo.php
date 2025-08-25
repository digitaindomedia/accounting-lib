<?php
namespace Icso\Accounting\Repositories\Pembelian\Received;


use App\Enums\JurnalStatusEnum;
use App\Enums\SettingEnum;
use App\Enums\StatusEnum;
use App\Enums\TransactionType;
use App\Enums\TypeEnum;
use App\Models\Tenant\Akuntansi\JurnalTransaksi;
use App\Models\Tenant\Pembelian\Order\PurchaseOrderProduct;
use App\Models\Tenant\Pembelian\Pembayaran\PurchasePaymentMeta;
use App\Models\Tenant\Pembelian\Penerimaan\PurchaseReceived;
use App\Models\Tenant\Pembelian\Penerimaan\PurchaseReceivedMeta;
use App\Models\Tenant\Pembelian\Penerimaan\PurchaseReceivedProduct;
use App\Models\Tenant\Pembelian\Retur\PurchaseReturProduct;
use App\Models\Tenant\Persediaan\Inventory;
use App\Repositories\ElequentRepository;
use App\Repositories\Tenant\Akuntansi\JurnalTransaksiRepo;
use App\Repositories\Tenant\Pembelian\Order\OrderRepo;
use App\Repositories\Tenant\Persediaan\Inventory\Interface\InventoryRepo;
use App\Repositories\Tenant\Utils\SettingRepo;
use App\Services\FileUploadService;
use App\Utils\Constants;
use App\Utils\Helpers;
use App\Utils\KeyNomor;
use App\Utils\TransactionsCode;
use App\Utils\Utility;
use App\Utils\VarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiveRepo extends ElequentRepository
{

    protected $model;

    public function __construct(PurchaseReceived $model)
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
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('receive_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('receive_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('receive_date','desc')->get();
        return $dataSet;
    }

    public function getAllDataBetweenBy($search, $page, $perpage, array $where = [], array $whereBetween=[])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('receive_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhereHas('order', function ($query) use ($search) {
                $query->where('order_no', 'like', '%' .$search. '%');
            });
            $query->orWhereHas('vendor', function ($query) use ($search) {
                $query->where('vendor_company_name', 'like', '%' .$search. '%');
            });
            $query->orWhereHas('warehouse', function ($query) use ($search) {
                $query->where('warehouse_name', 'like', '%' .$search. '%');
            });
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->when(!empty($whereBetween), function ($query) use($whereBetween){
            $query->whereBetween('receive_date', $whereBetween);
        })->orderBy('receive_date','desc')->with(['vendor', 'order', 'warehouse','receiveproduct','receiveproduct.product','receiveproduct.unit','receiveproduct.tax'])->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBetweenBy($search, array $where = [], array $whereBetween=[])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('receive_no', 'like', '%' .$search. '%');
            $query->orWhere('note', 'like', '%' .$search. '%');
            $query->orWhereHas('order', function ($query) use ($search) {
                $query->where('order_no', 'like', '%' .$search. '%');
            });
            $query->orWhereHas('vendor', function ($query) use ($search) {
                $query->where('vendor_company_name', 'like', '%' .$search. '%');
            });
            $query->orWhereHas('warehouse', function ($query) use ($search) {
                $query->where('warehouse_name', 'like', '%' .$search. '%');
            });
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->when(!empty($whereBetween), function ($query) use($whereBetween){
            $query->whereBetween('receive_date', $whereBetween);
        })->orderBy('receive_date','desc')->with(['vendor', 'order', 'warehouse'])->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $inventoryRepo = new InventoryRepo(new Inventory());
        $id = $request->id;
        $receivedNo = $request->received_no;
        if(empty($receivedNo)){
            $receivedNo = self::generateCodeTransaction(new PurchaseReceived(),KeyNomor::NO_PENERIMAAN_PEMBELIAN,'receive_no','receive_date');
        }
        $receivedDate = !empty($request->received_date) ? Utility::changeDateFormat($request->received_date) : date("Y-m-d");
        $order = json_decode(json_encode($request->order));
        $note = !empty($request->note) ? $request->note : "";
        $vendorId = $order->vendor->id;
        $warehouseId = $request->warehouse_id;
        $deliveryNo = $request->surat_jalan_no;
        $orderId = $request->order_id;
        $userId = $request->user_id;

        $receiveData = array(
            'receive_date' => $receivedDate,
            'receive_no' => $receivedNo,
            'surat_jalan_no' => !empty($deliveryNo) ? $deliveryNo : "",
            'note' => $note,
            'updated_by' => $userId,
            'order_id' => $orderId,
            'warehouse_id' => $warehouseId,
            'vendor_id' => $vendorId,
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
                $products = json_decode(json_encode($request->receiveproduct));
                if (count($products) > 0) {
                    foreach ($products as $item) {
                        $getDetailHpp = $this->getHppPrice($item->qty, $item->order_product_id);
                        $subtotal = Helpers::hitungSubtotal($item->qty,$item->buy_price,$item->discount,$item->discount_type);
                        $arrItem = array(
                            'receive_id' => $recId,
                            'qty' => $item->qty,
                            'qty_left' => $item->qty,
                            'product_id' => $item->product_id,
                            'unit_id' => $item->unit_id,
                            'order_product_id' => $item->order_product_id,
                            'multi_unit' => '0',
                            'hpp_price' => $getDetailHpp['hpp_price'],
                            'buy_price' => $getDetailHpp['buy_price'],
                            'tax_id' => $getDetailHpp['tax_id'],
                            'tax_percentage' => $getDetailHpp['tax_percentage'],
                            'tax_group' => $getDetailHpp['tax_group'],
                            'discount' => $getDetailHpp['discount'],
                            'subtotal' => $subtotal,
                            'tax_type' => $getDetailHpp['tax_type'],
                            'discount_type' => $getDetailHpp['discount_type'],
                        );
                        $resItem = PurchaseReceivedProduct::create($arrItem);
                        $req = new Request();
                        $req->coa_id = !empty($getDetailHpp['coa_id']) ? $getDetailHpp['coa_id'] : 0;
                        $req->user_id = $userId;
                        $req->inventory_date = $receivedDate;
                        $req->transaction_code = TransactionsCode::PENERIMAAN;
                        $req->qty_in = $item->qty;
                        $req->warehouse_id = $warehouseId;
                        $req->product_id = $item->product_id;
                        $req->price = $getDetailHpp['hpp_price'];
                        $req->note = $note;
                        $req->unit_id = $item->unit_id;
                        $req->transaction_id = $recId;
                        $req->transaction_sub_id = $resItem->id;
                        $inventoryRepo->store($req);
                    }
                }
                $this->postingJurnal($recId);
                OrderRepo::changeStatusPenerimaan($orderId);
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
                                PurchaseReceivedMeta::create($arrUpload);
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
        $find = PurchaseReceived::find($id);
        PurchaseReceivedProduct::where(array('receive_id' => $id))->delete();
        PurchaseReceivedMeta::where(array('receive_id' => $id))->delete();
        Inventory::where(array('transaction_code' => TransactionsCode::PENERIMAAN, 'transaction_id' => $id))->delete();
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PENERIMAAN, $id);
        OrderRepo::changeStatusPenerimaan($find->order_id);
    }

    public function postingJurnal($id){
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $coaUtangBelumRealisasi = SettingRepo::getOptionValue(SettingEnum::COA_UTANG_USAHA_BELUM_REALISASI);
        $coaSediaan = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN);
        $find = $this->findOne($id,array(),['receiveproduct','receiveproduct.product']);
        if(!empty($find)){
            $recDate = $find->receive_date;
            $recNo = $find->receive_no;
            $subtotal = 0;
            if(!empty($find->receiveproduct)){
                $recProduct = $find->receiveproduct;
                if(count($recProduct) > 0){
                    foreach ($recProduct as $item){
                        $product = $item->product;
                        $productName = "";
                        if(!empty($product)){
                            if(!empty($product->coa_id)){
                                $coaSediaan = $product->coa_id;
                                $productName = $product->item_name;
                            }
                        }
                        $noteProduct = !empty($productName) ? " dengan nama ".$productName : "";
                        $subtotalHpp = $item->hpp_price * $item->qty;
                        $arrJurnalDebet = array(
                            'transaction_date' => $recDate,
                            'transaction_datetime' => $recDate." ".date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::PENERIMAAN,
                            'coa_id' => $coaSediaan,
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => $item->id,
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $recNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => $subtotalHpp,
                            'kredit' => 0,
                            'note' => !empty($find->note) ? $find->note : 'Penerimaan Barang'.$noteProduct,
                        );
                        $jurnalTransaksiRepo->create($arrJurnalDebet);
                        $subtotal = $subtotal + $subtotalHpp;
                    }
                    $arrJurnalKredit = array(
                        'transaction_date' => $recDate,
                        'transaction_datetime' => $recDate." ".date('H:i:s'),
                        'created_by' => $find->created_by,
                        'updated_by' => $find->created_by,
                        'transaction_code' => TransactionsCode::PENERIMAAN,
                        'coa_id' => $coaUtangBelumRealisasi,
                        'transaction_id' => $find->id,
                        'transaction_sub_id' => 0,
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                        'transaction_no' => $recNo,
                        'transaction_status' => JurnalStatusEnum::OK,
                        'debet' => 0,
                        'kredit' => $subtotal,
                        'note' => !empty($find->note) ? $find->note : 'Penerimaan Barang',
                    );
                    $jurnalTransaksiRepo->create($arrJurnalKredit);
                }
            }
        }
    }

    public function getHppPrice($qty, $orderProductId){
        $subtotal = 0;
        $diskon = 0;
        $taxId = 0;
        $percentTax = 0;
        $taxGroup = '';
        $taxType ="";
        $discountType = "";
        $price = 0;
        $coaId=0;
        $findProductOrder = PurchaseOrderProduct::where(array('id' => $orderProductId))->with(['product'])->first();
        if(!empty($findProductOrder)){
            $price = $findProductOrder->price;
            $percentTax = $findProductOrder->tax_percentage;
            $taxGroup = $findProductOrder->tax_group;
            $total = $qty * $price;
            $product = $findProductOrder->product;
            $taxId = $findProductOrder->tax_id;
            $taxType = $findProductOrder->tax_type;
            $discountType = $findProductOrder->discount_type;
            $coaId = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN);
            $subtotal = $total;
            if(!empty($product))
            {
                if(!empty($product->coa_id)){
                    $coaId = $product->coa_id;
                }
                if(!empty($findProductOrder->discount)){
                    if($findProductOrder->discount_type == TypeEnum::DISCOUNT_TYPE_PERCENT){
                        $diskon = ($findProductOrder->discount/100) * $total;
                    } else {
                        $totalBawah = $findProductOrder->qty * $price;
                        $diskon = Helpers::hitungProporsi($total,$totalBawah,$findProductOrder->discount);
                    }
                    $total = $total - $diskon;
                }
                if(!empty($taxId)){
                    $getDataTax = Helpers::hitungTaxDpp($total,$taxId,$taxType,$percentTax);
                    if(!empty($getDataTax)){
                        $subtotal = $getDataTax[TypeEnum::DPP];
                    }
                }

            }
        }
        $hpp = $subtotal/$qty;
        return array(
            'subtotal' => $subtotal,
            'hpp_price' => $hpp,
            'buy_price' => $price,
            'tax_id' => $taxId,
            'tax_percentage' => $percentTax,
            'tax_group' => $taxGroup,
            'discount' => $diskon,
            'tax_type' => $taxType,
            'coa_id' => $coaId,
            'discount_type' => $discountType
        );

    }

    public function getTotalReceived($id){
        $find = $this->findOne($id,array(),['receiveproduct','receiveproduct.orderproduct']);
        $total = 0;
        $totalTax=0;
        $totalDiskon=0;
        if(!empty($find)){
            $receiveProduct = $find->receiveproduct;
            if(!empty($receiveProduct)){
                foreach ($receiveProduct as $key => $item){
                    //$orderProduct = $item->orderproduct;
                    $price = $item->buy_price;
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

    public static function getTotalReceivedByHpp($id){
        $find = (new self(new PurchaseReceived()))->findOne($id,array(),['receiveproduct','receiveproduct.orderproduct']);
        $total = 0;
        if(!empty($find)) {
            $receiveProduct = $find->receiveproduct;
            if (!empty($receiveProduct)) {
                foreach ($receiveProduct as $key => $item) {
                    $price = $item->hpp_price;
                    $subtotal = $item->qty * $price;
                    $total = $total + $subtotal;
                }
            }
        }
        return $total;
    }

    public function getQtyRetur($recProductId)
    {
        $qty = PurchaseReturProduct::where(array('receive_product_id' => $recProductId))->sum('qty');
        return $qty;
    }

    public static function getReceivedProduct($productId, $recId, $unitId){
        $totalQty = PurchaseReceivedProduct::where(array('product_id' => $productId, 'receive_id' => $recId, 'unit_id' => $unitId))->sum('qty');
        return $totalQty;
    }

    public function getTransaksi($idPenerimaan): array
    {
        $arrTransaksi = array();
        $find = $this->findOne($idPenerimaan,array(),['retur','invoicereceived','invoicereceived.invoice','invoicereceived.invoice.payment.purchasepayment']);
        if(!empty($find)){
            if(!empty($find->invoicereceived))
            {
                foreach ($find->invoicereceived as $item){
                    if(!empty($item->invoice)){
                        $invoice = $item->invoice;
                        $arrTransaksi[] = array(
                            VarType::TRANSACTION_DATE => $invoice->invoice_date,
                            VarType::TRANSACTION_TYPE => TransactionType::PURCHASE_INVOICE,
                            VarType::TRANSACTION_NO => $invoice->invoice_no,
                            VarType::TRANSACTION_ID => $invoice->id
                        );
                        if(!empty($invoice->payment)){
                            foreach ($invoice->payment as $val){
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
                    }

                }
                if(!empty($find->retur)){
                    foreach ($find->retur as $val){
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

    public static function changeStatusPenerimaanById($id,$status= StatusEnum::SELESAI)
    {
        $instance = (new self(new PurchaseReceived()));
        $arrUpdateStatus = array(
            'receive_status' => $status
        );
        $instance->update($arrUpdateStatus, $id);

    }

}
