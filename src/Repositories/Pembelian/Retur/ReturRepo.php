<?php

namespace Icso\Accounting\Repositories\Pembelian\Retur;


use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Pembelian\Pembayaran\PurchasePaymentInvoice;
use Icso\Accounting\Models\Pembelian\Retur\PurchaseRetur;
use Icso\Accounting\Models\Pembelian\Retur\PurchaseReturMeta;
use Icso\Accounting\Models\Pembelian\Retur\PurchaseReturProduct;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Pembelian\Invoice\InvoiceRepo;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
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

class ReturRepo extends ElequentRepository
{
    protected $model;

    public function __construct(PurchaseRetur $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = []): mixed
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($where), function ($query) use($where){
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
        })->when(!empty($search), function ($query) use($search){
            $query->where(function ($queryTopLevel) use($search){
                $queryTopLevel->where('retur_no', 'like', '%' .$search. '%');
                $queryTopLevel->orWhereHas('vendor', function ($queryVendor) use($search) {
                    $queryVendor->where('vendor_name', 'like', '%' .$search. '%');
                    $queryVendor->orWhere('vendor_company_name', 'like', '%' .$search. '%');
                });
                $queryTopLevel->orWhereHas('receive', function ($queryReceive) use($search) {
                    $queryReceive->where('receive_no', 'like', '%' .$search. '%');
                });
            });
        })->orderBy('retur_date','desc')->with(['vendor','receive','invoice','returproduct','returproduct.product', 'returproduct.tax', 'returproduct.tax.taxgroup.tax','returproduct.unit'])->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = []): int
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($where), function ($query) use($where){
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
        })->when(!empty($search), function ($query) use($search){
            $query->where(function ($queryTopLevel) use($search){
                $queryTopLevel->where('retur_no', 'like', '%' .$search. '%');
                $queryTopLevel->orWhereHas('vendor', function ($queryVendor) use($search) {
                    $queryVendor->where('vendor_name', 'like', '%' .$search. '%');
                    $queryVendor->orWhere('vendor_company_name', 'like', '%' .$search. '%');
                });
                $queryTopLevel->orWhereHas('receive', function ($queryReceive) use($search) {
                    $queryReceive->where('receive_no', 'like', '%' .$search. '%');
                });
            });
        })->orderBy('retur_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = []): bool
    {
        $id = $request->id;
        $returNo = $request->retur_no;
        if(empty($returNo)){
            $returNo = self::generateCodeTransaction(new PurchaseRetur(),KeyNomor::NO_RETUR_PEMBELIAN, 'retur_no','retur_date');
        }
        $returDate = !empty($request->retur_date) ? Utility::changeDateFormat($request->retur_date) : date('Y-m-d');
        $note = $request->note;
        $subtotal = Utility::remove_commas($request->subtotal);
        $totalTax = !empty($request->total_tax) ? Utility::remove_commas($request->total_tax) : 0;
        $total = !empty($request->total) ? Utility::remove_commas($request->total) : 0;
        $vendorId = !empty($request->vendor_id) ? Utility::remove_commas($request->vendor_id) : 0;
        $receiveId = !empty($request->receive_id) ? Utility::remove_commas($request->receive_id) : 0;
        $invoiceId = !empty($request->invoice_id) ? Utility::remove_commas($request->invoice_id) : 0;
        $userId = $request->user_id;
        $inventoryRepo = new InventoryRepo(new Inventory());
        $arrData = array(
            'retur_no' => $returNo,
            'retur_date' => $returDate,
            'note' => $note,
            'subtotal' => $subtotal,
            'total_tax' => $totalTax,
            'total' => $total,
            'vendor_id' => $vendorId,
            'receive_id' => $receiveId,
            'invoice_id' => $invoiceId,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        );
        DB::beginTransaction();
        try {
            if (empty($id)) {
                $statusRetur = StatusEnum::OPEN;
                if(!empty($request->invoice_id)){
                    $getStatus = InvoiceRepo::getStatusInvoice($request->invoice_id);
                    if($getStatus == StatusEnum::BELUM_LUNAS){
                        $statusRetur = StatusEnum::SELESAI;
                    }
                }
                $arrData['retur_status'] = $statusRetur;
                $arrData['reason'] = "";
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $arrData['created_by'] = $userId;
                $res = $this->create($arrData);
            } else {
                $res = $this->update($arrData, $id);
            }
            if ($res) {
                if(!empty($id)){
                    $this->deleteAdditional($id);
                    $idRetur = $id;
                } else {
                    $idRetur = $res->id;
                }
                $products = json_decode(json_encode($request->returproduct));
                if(count($products) > 0) {
                    foreach ($products as $item) {
                        $arrItem = array(
                            'qty' => $item->qty,
                            'product_id' => $item->product_id,
                            'unit_id' => $item->unit_id,
                            'tax_id' => $item->tax_id,
                            'tax_percentage' => $item->tax_percentage,
                            'hpp_price' => !empty($item->hpp_price) ? Utility::remove_commas($item->hpp_price) : 0,
                            'buy_price' => !empty($item->buy_price) ? Utility::remove_commas($item->buy_price) : 0,
                            'tax_type' => !empty($item->tax_type) ? $item->tax_type : '',
                            'discount_type' => !empty($item->discount_type) ? $item->discount_type : '',
                            'discount' => !empty($item->discount) ? Utility::remove_commas($item->discount) : 0,
                            'subtotal' => !empty($item->subtotal) ? Utility::remove_commas($item->subtotal) : 0,
                            'receive_product_id' => !empty($item->receive_product_id) ? $item->receive_product_id : 0,
                            'order_product_id' => !empty($item->order_product_id) ? $item->order_product_id : 0,
                            'multi_unit' => 0,
                            'retur_id' => $idRetur,
                        );
                        $resItem = PurchaseReturProduct::create($arrItem);
                        $req = new Request();
                        $req->coa_id = !empty($item->product) ? $item->product->coa_id : 0;
                        $req->user_id = $userId;
                        $req->inventory_date = $returDate;
                        $req->transaction_code = TransactionsCode::RETUR_PEMBELIAN;
                        $req->qty_out = $item->qty;
                        $req->warehouse_id = $request->warehouse_id;
                        $req->product_id = $item->product_id;
                        $req->price = !empty($item->hpp_price) ? Utility::remove_commas($item->hpp_price) : 0;
                        $req->note = $note;
                        $req->unit_id = $item->unit_id;
                        $req->transaction_id = $idRetur;
                        $req->transaction_sub_id = $resItem->id;
                        $inventoryRepo->store($req);
                    }
                }
                InvoiceRepo::insertIntoPaymentFromRetur($request->invoice_id,$idRetur,$returDate,$total);
                $this->postingJurnal($idRetur);
                $fileUpload = new FileUploadService();
                $uploadedFiles = $request->file('files');
                if(!empty($uploadedFiles)) {
                    if (count($uploadedFiles) > 0) {
                        foreach ($uploadedFiles as $file) {
                            // Handle each file as needed
                            $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                            if ($resUpload) {
                                $arrUpload = array(
                                    'retur_id' => $idRetur,
                                    'meta_key' => 'upload',
                                    'meta_value' => $resUpload
                                );
                                PurchaseReturMeta::create($arrUpload);
                            }
                        }
                    }
                }
                DB::commit();
                return true;
            } else {
                return false;
            }
        }catch (\Exception $e) {
            // Rollback Transaction
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function deleteAdditional($idRetur){
        PurchaseReturProduct::where(array('retur_id' => $idRetur))->delete();
        PurchaseReturMeta::where(array('retur_id' => $idRetur))->delete();
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::RETUR_PEMBELIAN, $idRetur);
        Inventory::where(array('transaction_code' => TransactionsCode::RETUR_PEMBELIAN, 'transaction_id' => $idRetur))->delete();
        PurchasePaymentInvoice::where(array('retur_id' => $idRetur))->delete();
    }

    public function postingJurnal($id){
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $coaUtangUsaha = SettingRepo::getOptionValue(SettingEnum::COA_UTANG_USAHA);
        $coaSediaan = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN);
        $coaPpn = SettingRepo::getOptionValue(SettingEnum::COA_PPN_MASUKAN);
        $find = $this->findOne($id,array(),['returproduct','returproduct.product','vendor']);
        if(!empty($find)){
            $returDate = $find->retur_date;
            $returNo = $find->retur_no;
            $subtotal = 0;
            $totalUtangUsaha = $find->total;
            $arrJurnalDebet = array(
                'transaction_date' => $returDate,
                'transaction_datetime' => $returDate." ".date('H:i:s'),
                'created_by' => $find->created_by,
                'updated_by' => $find->created_by,
                'transaction_code' => TransactionsCode::RETUR_PEMBELIAN,
                'coa_id' => $coaUtangUsaha,
                'transaction_id' => $find->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $returNo,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => $totalUtangUsaha,
                'kredit' => 0,
                'note' => !empty($find->note) ? $find->note : 'Retur Pembelian dengan nama supplier '.$find->vendor->vendor_name,
            );
            $jurnalTransaksiRepo->create($arrJurnalDebet);
            if(!empty($find->returproduct)){
                $returProduct = $find->returproduct;
                if(count($returProduct) > 0){
                    foreach ($returProduct as $item){
                        $product = $item->product;
                        $productName = "";
                        if(!empty($product)){
                            if(!empty($product->coa_id)){
                                $coaSediaan = $product->coa_id;
                                $productName = $product->item_name;
                            }
                        }
                        $subtotalHpp = $item->qty * $item->hpp_price;
                        $arrJurnalKredit = array(
                            'transaction_date' => $returDate,
                            'transaction_datetime' => $returDate." ".date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::RETUR_PEMBELIAN,
                            'coa_id' => $coaSediaan,
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => $item->id,
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $returNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => 0,
                            'kredit' => $subtotalHpp,
                            'note' => !empty($find->note) ? $find->note : 'Retur Pembelian dengan nama supplier '.$find->vendor->vendor_name,
                        );
                        $jurnalTransaksiRepo->create($arrJurnalKredit);
                        if(!empty($item->tax_id)){
                            $getTax = Helpers::hitungTaxDpp($item->subtotal,$item->tax_id, $item->tax_type,$item->tax_percentage);
                            if($getTax[TypeEnum::TAX_TYPE] == VarType::TAX_TYPE_SINGLE)
                            {
                                $arrJurnalKredit = array(
                                    'transaction_date' => $returDate,
                                    'transaction_datetime' => $returDate." ".date('H:i:s'),
                                    'created_by' => $find->created_by,
                                    'updated_by' => $find->created_by,
                                    'transaction_code' => TransactionsCode::RETUR_PEMBELIAN,
                                    'coa_id' => $getTax['purchase_coa_id'],
                                    'transaction_id' => $find->id,
                                    'transaction_sub_id' => $item->id,
                                    'created_at' => date("Y-m-d H:i:s"),
                                    'updated_at' => date("Y-m-d H:i:s"),
                                    'transaction_no' => $returNo,
                                    'transaction_status' => JurnalStatusEnum::OK,
                                    'debet' => 0,
                                    'kredit' => $getTax[TypeEnum::PPN],
                                    'note' => !empty($find->note) ? $find->note : 'Retur Pembelian dengan nama supplier '.$find->vendor->vendor_name,
                                );
                                $jurnalTransaksiRepo->create($arrJurnalKredit);
                            }
                        }
                    }
                }
            }
        }
    }
}
