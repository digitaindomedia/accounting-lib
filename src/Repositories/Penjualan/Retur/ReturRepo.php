<?php

namespace Icso\Accounting\Repositories\Penjualan\Retur;


use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Pembelian\Pembayaran\PurchasePaymentInvoice;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicingDelivery;
use Icso\Accounting\Models\Penjualan\Retur\SalesRetur;
use Icso\Accounting\Models\Penjualan\Retur\SalesReturMeta;
use Icso\Accounting\Models\Penjualan\Retur\SalesReturProduct;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Penjualan\Invoice\InvoiceRepo;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Services\FileUploadService;
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

    public function __construct(SalesRetur $model)
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
                $queryTopLevel->orWhereHas('delivery', function ($queryReceive) use($search) {
                    $queryReceive->where('retur_no', 'like', '%' .$search. '%');
                });
                $queryTopLevel->orWhereHas('invoice', function ($queryReceive) use($search) {
                    $queryReceive->where('invoice_no', 'like', '%' .$search. '%');
                });
            });
        })->orderBy('retur_date','desc')->with(['vendor','delivery','returproduct','returproduct.product','returproduct.unit','returproduct.tax','returproduct.tax.taxgroup','returproduct.deliveryproduct','returproduct.deliveryproduct.product'])->offset($page)->limit($perpage)->get();
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
                $queryTopLevel->orWhereHas('delivery', function ($queryReceive) use($search) {
                    $queryReceive->where('retur_no', 'like', '%' .$search. '%');
                });
                $queryTopLevel->orWhereHas('invoice', function ($queryReceive) use($search) {
                    $queryReceive->where('invoice_no', 'like', '%' .$search. '%');
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
            $returNo = self::generateCodeTransaction(new SalesRetur(),KeyNomor::NO_RETUR_PENJUALAN, 'retur_no','retur_date');
        }
        $returDate = !empty($request->retur_date) ? Utility::changeDateFormat($request->retur_date) : date('Y-m-d');
        $note = $request->note;
        $subtotal = Utility::remove_commas($request->subtotal);
        $totalTax = !empty($request->total_tax) ? Utility::remove_commas($request->total_tax) : 0;
        $total = !empty($request->total) ? Utility::remove_commas($request->total) : 0;
        $vendorId = !empty($request->vendor_id) ? Utility::remove_commas($request->vendor_id) : 0;
        $deliveryId = !empty($request->delivery_id) ? Utility::remove_commas($request->delivery_id) : 0;
        $invoiceId = !empty($request->invoice_id) ? Utility::remove_commas($request->invoice_id) : 0;
        $userId = $request->user_id;
        $arrData = array(
            'retur_no' => $returNo,
            'retur_date' => $returDate,
            'note' => $note,
            'subtotal' => $subtotal,
            'total_tax' => $totalTax,
            'total' => $total,
            'vendor_id' => $vendorId,
            'delivery_id' => $deliveryId,
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
                            'sell_price' => !empty($item->buy_price) ? Utility::remove_commas($item->sell_price) : 0,
                            'tax_type' => !empty($item->tax_type) ? $item->tax_type : '',
                            'discount_type' => !empty($item->discount_type) ? $item->discount_type : '',
                            'discount' => !empty($item->discount) ? Utility::remove_commas($item->discount) : 0,
                            'subtotal' => !empty($item->subtotal) ? Utility::remove_commas($item->subtotal) : 0,
                            'delivery_product_id' => !empty($item->delivery_product_id) ? $item->delivery_product_id : 0,
                            'order_product_id' => !empty($item->order_product_id) ? $item->order_product_id : 0,
                            'multi_unit' => 0,
                            'retur_id' => $idRetur,
                        );
                        $resItem = SalesReturProduct::create($arrItem);
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
                                SalesReturMeta::create($arrUpload);
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

    public function postingJurnal($idRetur)
    {
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $find = $this->findOne($idRetur,array(),['returproduct','returproduct.tax','returproduct.tax.taxgroup','returproduct.deliveryproduct','returproduct.deliveryproduct.product']);
        //cek sdh invoice apa belum buat jurnal sediaan balik
        $dppRetur = 0;
        $totalTax = 0;
        $noRetur = $find->retur_no;
        $returDate = $find->retur_date;
        $arrTax = array();
        $coaReturPenjualan =SettingRepo::getOptionValue(SettingEnum::COA_RETUR_PENJUALAN);
        $coaPiutangUsaha = SettingRepo::getOptionValue(SettingEnum::COA_PIUTANG_USAHA);
        if(!empty($find->delivery_id)){
            $findInInvoice = SalesInvoicingDelivery::where(array('delivery_id' => $find->delivery_id))->count();
            $coaSediaanBalik =SettingRepo::getOptionValue(SettingEnum::COA_BEBAN_POKOK_PENJUALAN);
            if($findInInvoice == 0){
                $coaSediaanBalik =SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN_DALAM_PERJALANAN);
            }
            $retDataSediaan = $this->postingJurnalSediaan($find,$coaSediaanBalik);
            $dppRetur = $retDataSediaan['dpp'];
            $arrTax = $retDataSediaan['tax'];
            $totalTax = $retDataSediaan['total_tax'];
        }
        $totalPiutangUsaha = $dppRetur + $totalTax;
        $arrJurnalDebet = array(
            'transaction_date' => $returDate,
            'transaction_datetime' => $returDate." ".date('H:i:s'),
            'created_by' => $find->created_by,
            'updated_by' => $find->created_by,
            'transaction_code' => TransactionsCode::RETUR_PENJUALAN,
            'coa_id' => $coaReturPenjualan,
            'transaction_id' => $find->id,
            'transaction_sub_id' => 0,
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s"),
            'transaction_no' => $noRetur,
            'transaction_status' => JurnalStatusEnum::OK,
            'debet' => $dppRetur,
            'kredit' => 0,
            'note' => !empty($find->note) ? $find->note : 'Retur Penjualan',
        );
        $jurnalTransaksiRepo->create($arrJurnalDebet);

        if(count($arrTax) > 0){
            foreach ($arrTax as $val){
                $namaDetail = "";
                if(!empty($val['nama_item'])){
                    $namaDetail = ' dengan nama item '.$val['nama_item'];
                }
                else {
                    if(!empty($find->vendor)){
                        $namaDetail = ' dengan nama customer '.$find->vendor->vendor_company_name ;
                    }
                }
                if($val['posisi'] == 'debet'){

                    $arrJurnalDebet = array(
                        'transaction_date' => $returDate,
                        'transaction_datetime' => $returDate." ".date('H:i:s'),
                        'created_by' => $find->created_by,
                        'updated_by' => $find->created_by,
                        'transaction_code' => TransactionsCode::RETUR_PENJUALAN,
                        'coa_id' => $val['coa_id'],
                        'transaction_id' => $find->id,
                        'transaction_sub_id' => $val['id_item'],
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                        'transaction_no' => $noRetur,
                        'transaction_status' => JurnalStatusEnum::OK,
                        'debet' => $val['nominal'],
                        'kredit' => 0,
                        'note' => !empty($find->note) ? $find->note : 'Retur Penjualan'.$namaDetail,
                    );
                    $jurnalTransaksiRepo->create($arrJurnalDebet);
                } else
                {
                    $arrJurnalKredit = array(
                        'transaction_date' => $returDate,
                        'transaction_datetime' => $returDate." ".date('H:i:s'),
                        'created_by' => $find->created_by,
                        'updated_by' => $find->created_by,
                        'transaction_code' => TransactionsCode::RETUR_PENJUALAN,
                        'coa_id' => $val['coa_id'],
                        'transaction_id' => $find->id,
                        'transaction_sub_id' => $val['id_item'],
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                        'transaction_no' => $noRetur,
                        'transaction_status' => JurnalStatusEnum::OK,
                        'debet' => 0,
                        'kredit' => $val['nominal'],
                        'note' => !empty($find->note) ? $find->note : 'Retur Penjualan'.$namaDetail,
                    );
                    $jurnalTransaksiRepo->create($arrJurnalKredit);
                }
            }
        }
        $arrJurnalKredit = array(
            'transaction_date' => $returDate,
            'transaction_datetime' => $returDate." ".date('H:i:s'),
            'created_by' => $find->created_by,
            'updated_by' => $find->created_by,
            'transaction_code' => TransactionsCode::RETUR_PENJUALAN,
            'coa_id' => $coaPiutangUsaha,
            'transaction_id' => $find->id,
            'transaction_sub_id' => 0,
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s"),
            'transaction_no' => $noRetur,
            'transaction_status' => JurnalStatusEnum::OK,
            'debet' => 0,
            'kredit' => $totalPiutangUsaha,
            'note' => !empty($find->note) ? $find->note : 'Retur Penjualan',
        );
        $jurnalTransaksiRepo->create($arrJurnalKredit);

    }

    public function postingJurnalSediaan($find,$coaSediaanBalik)
    {
        $inventoryRepo = new InventoryRepo(new Inventory());
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $coaSediaan = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN);
        $dppPiutang = 0;
        $totalTax = 0;
        $arrTax = array();
        if(!empty($find)){
            $noRetur = $find->retur_no;
            $returDate = $find->retur_date;
            $returProduct = $find->returproduct;
            $totalAllSediaan = 0;
            if(!empty($returProduct)){
                foreach ($returProduct as $item){
                    $deliveryProduct = $item->deliveryproduct;
                    $product = $item->product;
                    $productName = "";
                    if(!empty($product)){
                        if(!empty($product->coa_id)){
                            $coaSediaan = $product->coa_id;

                        }
                        $productName = $product->item_name;
                    }
                    $noteProduct = !empty($productName) ? " dengan nama ".$productName : "";
                    $findInStok = $inventoryRepo->findByTransCodeIdSubId(TransactionsCode::DELIVERY_ORDER,$deliveryProduct->delivery_id, $deliveryProduct->id);
                    $hpp = 0;
                    if(!empty($findInStok)){
                        $hpp = $findInStok->nominal;
                    }
                    $subtotalHpp = $hpp * $item->qty;
                    $reqInventory = new Request();
                    $reqInventory->coa_id = $coaSediaan;
                    $reqInventory->user_id = $find->created_by;
                    $reqInventory->inventory_date = $returDate;
                    $reqInventory->transaction_code = TransactionsCode::RETUR_PENJUALAN;
                    $reqInventory->transaction_id = $find->id;
                    $reqInventory->transaction_sub_id = $item->id;
                    $reqInventory->qty_in = $item->qty;
                    $reqInventory->warehouse_id = $find->warehouse_id;
                    $reqInventory->product_id = $item->product_id;
                    $reqInventory->price = $hpp;
                    $reqInventory->note = $find->note;
                    $reqInventory->unit_id = $item->unit_id;
                    $inventoryRepo->store($reqInventory);
                    $arrJurnalDebet = array(
                        'transaction_date' => $returDate,
                        'transaction_datetime' => $returDate." ".date('H:i:s'),
                        'created_by' => $find->created_by,
                        'updated_by' => $find->created_by,
                        'transaction_code' => TransactionsCode::RETUR_PENJUALAN,
                        'coa_id' => $coaSediaan,
                        'transaction_id' => $find->id,
                        'transaction_sub_id' => $item->id,
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                        'transaction_no' => $noRetur,
                        'transaction_status' => JurnalStatusEnum::OK,
                        'debet' => $subtotalHpp,
                        'kredit' => 0,
                        'note' => !empty($find->note) ? $find->note : 'Retur Barang'.$noteProduct,
                    );
                    $jurnalTransaksiRepo->create($arrJurnalDebet);
                    $totalAllSediaan = $totalAllSediaan + $subtotalHpp;
                    $objTax = $item->tax;
                    $subtotal = $item->subtotal;
                    if(!empty($objTax)) {
                        if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
                            $posisi = "debet";
                            if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE) {
                                $pembagi = ($item->tax_percentage + 100) / 100;
                                $dpp = $item->subtotal / $pembagi;
                                $dppPiutang = $dppPiutang + $dpp;
                                $tax = ($item->tax_percentage / 100) * $dpp;
                                $totalTax = $totalTax + $tax;

                            } else {
                                $tax = ($item->tax_percentage / 100) * $subtotal;
                                $totalTax = $totalTax + $tax;
                                $dppPiutang = $dppPiutang + $item->subtotal;
                            }

                            if ($objTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                                $posisi = "kredit";
                            }
                            $arrTax[] = array(
                                'coa_id' => $objTax->sales_coa_id,
                                'posisi' => $posisi,
                                'nominal' => $tax,
                                'nama_item' => !empty($product) ? $product->item_name: $item->service_name,
                                'id_item' => $item->id
                            );
                        } else {
                            $tagGroups = $objTax->taxgroup;
                            if (!empty($tagGroups)) {
                                $total = $subtotal;
                                foreach ($tagGroups as $group) {
                                    $findTax = $group->tax;
                                    if (!empty($findTax)) {
                                        if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE) {
                                            $pembagi = ($findTax->tax_percentage + 100) / 100;
                                            $subtotal = $total / $pembagi;
                                        }
                                        $dppPiutang = $dppPiutang + $subtotal;
                                        $tax = ($findTax->tax_percentage / 100) * $subtotal;
                                        $totalTax = $totalTax + $tax;
                                        $posisi = "debet";
                                        if ($findTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                                            $posisi = "kredit";
                                        }
                                        $arrTax[] = array(
                                            'coa_id' => $findTax->sales_coa_id,
                                            'posisi' => $posisi,
                                            'nominal' => $tax,
                                            'nama_item' => $product->item_name,
                                            'id_item' => $item->id
                                        );
                                    }
                                }
                            }
                        }
                    }
                    else {
                        $dppPiutang = $dppPiutang +  $item->subtotal;
                    }
                }
            }
            if(!empty($totalAllSediaan)){
                $arrJurnalKredit = array(
                    'transaction_date' => $returDate,
                    'transaction_datetime' => $returDate." ".date('H:i:s'),
                    'created_by' => $find->created_by,
                    'updated_by' => $find->created_by,
                    'transaction_code' => TransactionsCode::RETUR_PENJUALAN,
                    'coa_id' => $coaSediaanBalik,
                    'transaction_id' => $find->id,
                    'transaction_sub_id' => $item->id,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_no' => $noRetur,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'debet' => 0,
                    'kredit' => $totalAllSediaan,
                    'note' => !empty($find->note) ? $find->note : 'Retur Barang',
                );
                $jurnalTransaksiRepo->create($arrJurnalKredit);
            }
        }
        return array(
            'dpp' => $dppPiutang,
            'tax' => $arrTax,
            'total_tax' => $totalTax
        );
    }

    public function deleteAdditional($idRetur){
        SalesReturProduct::where(array('retur_id' => $idRetur))->delete();
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::RETUR_PENJUALAN, $idRetur);
        Inventory::where(array('transaction_code' => TransactionsCode::RETUR_PENJUALAN, 'transaction_id' => $idRetur))->delete();
        PurchasePaymentInvoice::where(array('retur_id' => $idRetur))->delete();
        SalesReturMeta::where(array('retur_id' => $idRetur))->delete();
    }
}
