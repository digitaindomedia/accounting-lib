<?php

namespace Icso\Accounting\Repositories\Pembelian\Invoice;

use App\Enums\InvoiceStatusEnum;
use App\Enums\JurnalStatusEnum;
use App\Enums\SettingEnum;
use App\Enums\StatusEnum;
use App\Enums\TransactionType;
use App\Enums\TypeEnum;
use App\Models\Tenant\Akuntansi\JurnalTransaksi;
use App\Models\Tenant\Master\Product;
use App\Models\Tenant\Master\Tax;
use App\Models\Tenant\Pembelian\Invoicing\PurchaseInvoicing;
use App\Models\Tenant\Pembelian\Invoicing\PurchaseInvoicingDp;
use App\Models\Tenant\Pembelian\Invoicing\PurchaseInvoicingMeta;
use App\Models\Tenant\Pembelian\Invoicing\PurchaseInvoicingReceived;
use App\Models\Tenant\Pembelian\Order\PurchaseOrderProduct;
use App\Models\Tenant\Pembelian\Pembayaran\PurchasePaymentInvoice;
use App\Models\Tenant\Pembelian\Penerimaan\PurchaseReceived;
use App\Models\Tenant\Persediaan\Inventory;
use App\Repositories\ElequentRepository;
use App\Repositories\Tenant\Akuntansi\JurnalTransaksiRepo;
use App\Repositories\Tenant\Pembelian\Downpayment\DpRepo;
use App\Repositories\Tenant\Pembelian\Invoice\Interface\InvoiceInterface;
use App\Repositories\Tenant\Pembelian\Order\OrderRepo;
use App\Repositories\Tenant\Pembelian\Payment\PaymentInvoiceRepo;
use App\Repositories\Tenant\Pembelian\Received\ReceiveRepo;
use App\Repositories\Tenant\Persediaan\Inventory\Interface\InventoryRepo;
use App\Repositories\Tenant\Utils\SettingRepo;
use App\Services\FileUploadService;
use App\Utils\Helpers;
use App\Utils\InputType;
use App\Utils\KeyNomor;
use App\Utils\ProductType;
use App\Utils\TransactionsCode;
use App\Utils\Utility;
use App\Utils\VarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceRepo extends ElequentRepository
{

    protected $model;

    public function __construct(PurchaseInvoicing $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = []): mixed
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
       // $paymentInvoiceRepo = new PaymentInvoiceRepo(new PurchasePaymentInvoice());
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('invoice_no', 'like', '%' .$search. '%');
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
        })->with(['vendor', 'invoicereceived','invoicereceived.receive.warehouse','invoicereceived.receive.receiveproduct','invoicereceived.receive.receiveproduct.unit','invoicereceived.receive.receiveproduct.product','invoicereceived.receive.receiveproduct.tax','invoicereceived.receive.receiveproduct.tax.taxgroup','invoicereceived.receive.receiveproduct.tax.taxgroup.tax','order','orderproduct', 'orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup','orderproduct.tax.taxgroup.tax','orderproduct.unit'])->orderBy('invoice_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = []): int
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('invoice_no', 'like', '%' .$search. '%');
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
        // Initialize repository
        $inventoryRepo = new InventoryRepo(new Inventory());
        $userId = $request->user_id;
        // Gather input data
        $data = $this->gatherInputData($request);

        DB::beginTransaction();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            // Create or update invoice
            $invoice = $this->saveInvoice($data, $request->id, $userId);

            if ($invoice) {
                $idInvoice = $request->id ?? $invoice->id;
                if(!empty($request->id)){
                    $this->deleteAdditional($request->id);
                }
                // Handle order products
                $this->handleOrderProducts($request->orderproduct, $idInvoice, $data['tax_type'], $data['invoice_date'], $data['note'], $userId, $data['warehouse_id'], $request->input_type, $inventoryRepo);

                // Handle down payments
                $this->handleDownPayments($request->dp, $idInvoice);

                // Handle received products
                $this->handleReceivedProducts($request->receive, $idInvoice);

                // Post journal if necessary
                if ($data['input_type'] != InputType::JURNAL) {
                    $this->postingJurnal($idInvoice);
                }

                // Handle file uploads
                $this->handleFileUploads($request->file('files'), $idInvoice, $userId);

                DB::commit();
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                return true;
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                return false;
            }
        } catch (\Exception $e) {
            // Rollback transaction on error
            Log::error("Invoice Pembelian: ".$e->getMessage());
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            return false;
        }
    }

    /**
     * Gather input data from the request.
     */
    public function gatherInputData(Request $request): array
    {
        return [
            'id' => $request->id,
            'invoice_no' => $request->invoice_no ?: self::generateCodeTransaction(new PurchaseInvoicing(), KeyNomor::NO_INVOICE_PEMBELIAN, 'invoice_no', 'invoice_date'),
            'invoice_date' => Utility::changeDateFormat($request->invoice_date),
            'note' => $request->note ?? '',
            'due_date' => $request->due_date ? Utility::changeDateFormat($request->due_date) : date('Y-m-d'),
            'tax_type' => $request->tax_type ?? '',
            'discount_type' => $request->discount_type ?? '',
            'vendor_id' => $request->vendor_id,
            'invoice_type' => $request->invoice_type,
            'input_type' => $request->input_type,
            'order_id' => $request->order_id ?? '0',
            'dp_nominal' => $request->dp_nominal ?? '0',
            'coa_id' => $request->coa_id ?? '0',
            'jurnal_id' => $request->jurnal_id ?? '0',
            'warehouse_id' => $request->warehouse_id ?? '0',
            'subtotal' => Utility::remove_commas($request->subtotal),
            'dpp_total' => $request->dpp_total ? Utility::remove_commas($request->dpp_total) : '0',
            'discount' => $request->discount ? Utility::remove_commas($request->discount) : '0',
            'discount_total' => $request->discount_total ? Utility::remove_commas($request->discount_total) : '0',
            'tax_total' => $request->tax_total ? Utility::remove_commas($request->tax_total) : '0',
            'grandtotal' => Utility::remove_commas($request->grandtotal),
        ];
    }

    /**
     * Save invoice (create or update).
     */
    public function saveInvoice(array $data, ?string $id, string $userId)
    {
        $data['updated_by'] = $userId;
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (empty($id)) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['created_by'] = $userId;
            $data['invoice_status'] = InvoiceStatusEnum::BELUM_LUNAS;
            return $this->create($data);
        } else {
            $res = $this->update($data, $id);
            return $res;
        }
    }

    /**
     * Handle order products.
     */
    private function handleOrderProducts($orderProducts, string $invoiceId, string $taxType, string $invoiceDate, string $note, string $userId, string $warehouseId, string $inputType, InventoryRepo $inventoryRepo)
    {
        if (!empty($orderProducts)) {
            $products = json_decode(json_encode($orderProducts));
            if (count($products) > 0) {
                foreach ($products as $item) {
                    $this->saveOrderProduct($item, $invoiceId, $taxType, $invoiceDate, $note, $userId, $warehouseId, $inputType, $inventoryRepo);
                }
            }
        }
    }

    /**
     * Save a single order product.
     */
    public function saveOrderProduct($item, string $invoiceId, string $taxType, string $invoiceDate, string $note, string $userId, string $warehouseId,string $inputType, InventoryRepo $inventoryRepo)
    {
        $total = $item->subtotal ? Utility::remove_commas($item->subtotal) : 0;
        $hargaBeli = $item->price ? Utility::remove_commas($item->price) : 0;
        if($inputType == ProductType::ITEM){
            $findProduct = Product::find($item->product_id);
        }


        $arrItem = [
            'qty' => $item->qty,
            'service_name' => $item->service_name ?? "",
            'product_id' => $item->product_id ?? '0',
            'unit_id' => $item->unit_id ?? "0",
            'tax_id' => $item->tax_id ?? '0',
            'tax_percentage' => $item->tax_percentage ?? '0',
            'price' => $hargaBeli,
            'tax_type' => $taxType,
            'discount_type' => $item->discount_type ?? '',
            'discount' => $item->discount ? Utility::remove_commas($item->discount) : 0,
            'subtotal' => $total,
            'request_product_id' => $item->request_product_id ?? 0,
            'multi_unit' => 0,
            'order_id' => 0,
            'invoice_id' => $invoiceId
        ];

        $hpp = $hargaBeli;
        $resItem = PurchaseOrderProduct::create($arrItem);
        if($resItem){
            if($inputType == ProductType::ITEM) {
                if ($findProduct->product_type == ProductType::ITEM) {
                    if (!empty($item->tax_id)) {
                        if ($taxType == TypeEnum::TAX_TYPE_INCLUDE) {
                            $pembagi = ($item->tax_percentage + 100) / 100;
                            $subtotalHpp = $total / $pembagi;
                            $hpp = $subtotalHpp / $item->qty;
                        }
                    }
                    $this->addInventory($item, $invoiceDate, $note, $userId, $warehouseId, $inventoryRepo, $hpp, $invoiceId, $resItem->id);
                }
            }
        }
        return $resItem;
    }

    /**
     * Add inventory entry.
     */
    private function addInventory($item, string $invoiceDate, string $note, string $userId, string $warehouseId, InventoryRepo $inventoryRepo, float $hpp, string $invoiceId, string $resItemId)
    {
        $req = new Request();
        $req->coa_id = Product::find($item->product_id)->coa_id ?? 0;
        $req->user_id = $userId;
        $req->inventory_date = $invoiceDate;
        $req->transaction_code = TransactionsCode::INVOICE_PEMBELIAN;
        $req->qty_in = $item->qty;
        $req->warehouse_id = $warehouseId;
        $req->product_id = $item->product_id;
        $req->price = $hpp;
        $req->note = $note;
        $req->unit_id = $item->unit_id;
        $req->transaction_id = $invoiceId;
        $req->transaction_sub_id = $resItemId;
        $inventoryRepo->store($req);
    }

    /**
     * Handle down payments.
     */
    private function handleDownPayments($dps, string $invoiceId)
    {
        if (!empty($dps)) {
            $dps = json_decode(json_encode($dps));
            foreach ($dps as $dp) {
                PurchaseInvoicingDp::create([
                    'invoice_id' => $invoiceId,
                    'dp_id' => $dp->id
                ]);
            }
        }
    }

    /**
     * Handle received products.
     */
    private function handleReceivedProducts($receives, string $invoiceId)
    {
        if (!empty($receives)) {
            $receives = json_decode(json_encode($receives));
            if (count($receives) > 0) {
                foreach ($receives as $item) {
                    PurchaseInvoicingReceived::create([
                        'invoice_id' => $invoiceId,
                        'receive_id' => $item->id
                    ]);
                    OrderRepo::closeStatusOrderById($item->order_id);
                }
            }
        }
    }

    /**
     * Handle file uploads.
     */
    private function handleFileUploads($uploadedFiles, string $invoiceId, string $userId)
    {
        if (!empty($uploadedFiles)) {
            if (count($uploadedFiles) > 0) {
                $fileUpload = new FileUploadService();
                foreach ($uploadedFiles as $file) {
                    $resUpload = $fileUpload->upload($file, tenant(), $userId);
                    if ($resUpload) {
                        PurchaseInvoicingMeta::create([
                            'invoice_id' => $invoiceId,
                            'meta_key' => 'upload',
                            'meta_value' => $resUpload
                        ]);
                    }
                }
            }
        }
    }

    public function deleteAdditional($id)
    {
        $findDp = PurchaseInvoicingDp::where(array('invoice_id' => $id))->get();
        if(!empty($findDp)) {
            foreach ($findDp as $dp) {
                DpRepo::changeStatusUangMuka($dp->dp_id);
            }
        }
        $findReceived = PurchaseInvoicingReceived::where(array('invoice_id' => $id))->get();
        if(!empty($findReceived)) {
            foreach ($findReceived as $received) {
                $rec = PurchaseReceived::find($received->receive_id);
                if ($rec) {
                    $rec->update(['receive_status' => StatusEnum::OPEN]);
                }

            }
        }
        $findInvoice = PurchaseInvoicing::where('id','=',$id)->first();
        if(!empty($findInvoice)) {
            if(!empty($findInvoice->order_id)){
                OrderRepo::changeStatusPenerimaan($findInvoice->order_id);
            }
        }
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::INVOICE_PEMBELIAN, $id);
        PurchaseOrderProduct::where('invoice_id','=',$id)->delete();
        PurchaseInvoicingReceived::where(array('invoice_id' => $id))->delete();
        PurchaseInvoicingDp::where(array('invoice_id' => $id))->delete();
        PurchaseInvoicingMeta::where(array('invoice_id' => $id))->delete();
        Inventory::where('transaction_code','=',TransactionsCode::INVOICE_PEMBELIAN)->where('transaction_id','=',$id)->delete();
    }

    public function postingJurnal($idInvoice): void
    {
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $find = $this->findOne($idInvoice,array(),['vendor','orderproduct','orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup']);
        $coaSediaan = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN);
        $coaUtangUsahaBelumRealisasi = SettingRepo::getOptionValue(SettingEnum::COA_UTANG_USAHA_BELUM_REALISASI);
        $coaUtangUsaha = SettingRepo::getOptionValue(SettingEnum::COA_UTANG_USAHA);
        $coaPotongan = SettingRepo::getOptionValue(SettingEnum::COA_POTONGAN_PEMBELIAN);
        $coaUangMuka = SettingRepo::getOptionValue(SettingEnum::COA_UANG_MUKA_PEMBELIAN);
        $coaPpnMasukan = SettingRepo::getOptionValue(SettingEnum::COA_PPN_MASUKAN);
        $arrCoaProduct = array();
        $arrTax = array();
        if(!empty($find)){
            $invDate = $find->invoice_date;
            $invNo = $find->invoice_no;
            //pembelian langsung
            if(empty($find->order_id) || $find->invoice_type == ProductType::SERVICE){
                $invProduct = $find->orderproduct;
                if(count($invProduct) == 0){

                    $invProduct = PurchaseOrderProduct::where(array('order_id' => $find->order_id))->with(['product','tax','tax.taxgroup','tax.taxgroup.tax'])->get();
                }
                if(count($invProduct) > 0){
                    foreach ($invProduct as $key => $item){
                        $product = $item->product;
                        $objTax = $item->tax;
                        $hpp = $item->price;
                        $subtotal = $item->subtotal;
                        $tax = 0;
                        if(!empty($objTax)) {
                            if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
                                $posisi = "debet";
                                if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE) {
                                    if($objTax->is_dpp_nilai_Lain == 1){
                                        $hitung = Helpers::hitungIncludeTaxDppNilaiLain($item->tax_percentage,$item->subtotal);
                                        $tax = $hitung[TypeEnum::PPN];
                                        $dpp = $hitung[TypeEnum::DPP];
                                    }
                                    else {
                                        $hitung = Helpers::hitungIncludeTax($item->tax_percentage,$item->subtotal);
                                        $tax = $hitung[TypeEnum::PPN];
                                        $dpp = $hitung[TypeEnum::DPP];
                                    }

                                } else {
                                    if($objTax->is_dpp_nilai_Lain == 1){
                                        $hitung = Helpers::hitungExcludeTaxDppNilaiLain($item->tax_percentage,$subtotal);
                                        $tax = $hitung[TypeEnum::PPN];
                                        $dpp = $hitung[TypeEnum::DPP];
                                    }
                                    else {
                                        $hitung = Helpers::hitungExcludeTax($item->tax_percentage,$subtotal);
                                        $tax = $hitung[TypeEnum::PPN];
                                        $dpp = $hitung[TypeEnum::DPP];
                                    }

                                }

                                if ($objTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                                    $posisi = "kredit";
                                }
                                $arrTax[] = array(
                                    'coa_id' => $objTax->purchase_coa_id,
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
                                                if($findTax->is_dpp_nilai_Lain == 1){
                                                    $hitung = Helpers::hitungIncludeTaxDppNilaiLain($findTax->tax_percentage,$total);
                                                    $dpp = $hitung[TypeEnum::DPP];
                                                    $tax = $hitung[TypeEnum::PPN];
                                                }
                                                else {
                                                    $hitung = Helpers::hitungIncludeTax($findTax->tax_percentage,$total);
                                                    $dpp = $hitung[TypeEnum::DPP];
                                                    $tax = $hitung[TypeEnum::PPN];
                                                }

                                            }
                                           else{
                                               if($findTax->is_dpp_nilai_Lain == 1){
                                                   $hitung = Helpers::hitungExcludeTaxDppNilaiLain($findTax->tax_percentage,$total);
                                                   $dpp = $hitung[TypeEnum::DPP];
                                                   $tax = $hitung[TypeEnum::PPN];
                                               }
                                               else {
                                                   $hitung = Helpers::hitungExcludeTax($findTax->tax_percentage,$total);
                                                   $dpp = $hitung[TypeEnum::DPP];
                                                   $tax = $hitung[TypeEnum::PPN];
                                               }
                                           }
                                            $posisi = "debet";
                                            if ($findTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                                                $posisi = "kredit";
                                            }
                                            $arrTax[] = array(
                                                'coa_id' => $findTax->purchase_coa_id,
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

                        if(!empty($product)){
                            $coaSediaan = !empty($product->coa_id) ? $product->coa_id : $coaSediaan;
                            $arrCoaProduct[] = array(
                                'coa_id' => $coaSediaan,
                                'nominal' => $subtotal,
                                'nama_item' => $product->item_name,
                                'id_item' => $item->id
                            );
                        } else {
                            $arrCoaProduct[] = array(
                                'coa_id' => $find->coa_id,
                                'nominal' => $subtotal,
                                'nama_item' => $item->service_name,
                                'id_item' => $item->id
                            );
                        }
                    }
                }

                if(count($arrCoaProduct) > 0){
                    foreach ($arrCoaProduct as $val){
                        $arrJurnalDebet = array(
                            'transaction_date' => $invDate,
                            'transaction_datetime' => $invDate." ".date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN,
                            'coa_id' => $val['coa_id'],
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => $val['id_item'],
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $invNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => $val['nominal'],
                            'kredit' => 0,
                            'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian dengan nama item '.$val['nama_item'],
                        );
                        $jurnalTransaksiRepo->create($arrJurnalDebet);
                    }
                }
            } else {
                $findAllReceived = PurchaseInvoicingReceived::where(array('invoice_id' => $find->id))->with(['receive', 'receive.receiveproduct', 'receive.receiveproduct.tax','receive.receiveproduct.tax.taxgroup','receive.receiveproduct.tax.taxgroup.tax'])->get();
                if(count($findAllReceived) > 0){
                    foreach ($findAllReceived as $rec){
                        $received = $rec->receive;
                        if(!empty($received)){
                            $arrProductRec = $received->receiveproduct;
                            if(!empty($arrProductRec)){
                                foreach ($arrProductRec as $itemRec){
                                    $objTax = $itemRec->tax;
                                    if(!empty($objTax)){
                                        if($objTax->tax_type == VarType::TAX_TYPE_SINGLE){
                                            $posisi = "debet";
                                            if($itemRec->tax_type == TypeEnum::TAX_TYPE_INCLUDE){
                                                if($objTax->is_dpp_nilai_Lain == 1){
                                                    $hitung = Helpers::hitungIncludeTaxDppNilaiLain($itemRec->tax_percentage,$itemRec->subtotal);
                                                    $tax = $hitung[TypeEnum::PPN];
                                                    $dpp = $hitung[TypeEnum::DPP];
                                                }
                                                else {
                                                    $hitung = Helpers::hitungIncludeTax($itemRec->tax_percentage,$itemRec->subtotal);
                                                    $tax = $hitung[TypeEnum::PPN];
                                                    $dpp = $hitung[TypeEnum::DPP];
                                                }
                                            }
                                            else {
                                                if($objTax->is_dpp_nilai_Lain == 1){
                                                    $hitung = Helpers::hitungExcludeTaxDppNilaiLain($itemRec->tax_percentage,$itemRec->subtotal);
                                                    $tax = $hitung[TypeEnum::PPN];
                                                    $dpp = $hitung[TypeEnum::DPP];
                                                }
                                                else {
                                                    $hitung = Helpers::hitungExcludeTax($itemRec->tax_percentage,$itemRec->subtotal);
                                                    $tax = $hitung[TypeEnum::PPN];
                                                    $dpp = $hitung[TypeEnum::DPP];
                                                }
                                            }

                                            if($objTax->tax_sign == VarType::TAX_SIGN_PEMOTONG){
                                                $posisi = "kredit";
                                            }
                                            $arrTax[] = array(
                                                'coa_id' => $objTax->purchase_coa_id,
                                                'posisi' => $posisi,
                                                'nominal' => $tax,
                                                'id_item' => $itemRec->id
                                            );
                                        } else {
                                            $tagGroups = $objTax->taxgroup;
                                            if(!empty($tagGroups)){
                                                $total = $itemRec->subtotal;
                                                foreach ($tagGroups as $group){
                                                    $findTax = $group->tax;
                                                    if(!empty($findTax)){
                                                        if($itemRec->tax_type == TypeEnum::TAX_TYPE_INCLUDE){
                                                            if($findTax->tax_sign == VarType::TAX_SIGN_PENAMBAH){
                                                                if($findTax->is_dpp_nilai_Lain == 1){
                                                                    $hitung = Helpers::hitungIncludeTaxDppNilaiLain($findTax->tax_percentage,$total);
                                                                    $tax = $hitung[TypeEnum::PPN];
                                                                    $dpp = $hitung[TypeEnum::DPP];
                                                                }
                                                                else {
                                                                    $hitung = Helpers::hitungIncludeTax($findTax->tax_percentage,$total);
                                                                    $tax = $hitung[TypeEnum::PPN];
                                                                    $dpp = $hitung[TypeEnum::DPP];
                                                                }

                                                            }
                                                        } else {
                                                            if($findTax->is_dpp_nilai_Lain == 1){
                                                                $hitung = Helpers::hitungExcludeTaxDppNilaiLain($findTax->tax_percentage,$total);
                                                                $tax = $hitung[TypeEnum::PPN];
                                                                $dpp = $hitung[TypeEnum::DPP];
                                                            }
                                                            else {
                                                                $hitung = Helpers::hitungExcludeTax($findTax->tax_percentage,$total);
                                                                $tax = $hitung[TypeEnum::PPN];
                                                                $dpp = $hitung[TypeEnum::DPP];
                                                            }

                                                        }

                                                        $posisi = "debet";
                                                        if($findTax->tax_sign == VarType::TAX_SIGN_PEMOTONG){
                                                            $posisi = "kredit";
                                                        }
                                                        $arrTax[] = array(
                                                            'coa_id' => $findTax->purchase_coa_id,
                                                            'posisi' => $posisi,
                                                            'nominal' => $tax,
                                                            'id_item' => $itemRec->id
                                                        );
                                                    }
                                                }
                                            }
                                        }
                                    }

                                }
                            }
                            ReceiveRepo::changeStatusPenerimaanById($rec->receive_id);
                        }
                        $totalHpRec = ReceiveRepo::getTotalReceivedByHpp($rec->receive_id);
                        $arrJurnalDebet = array(
                            'transaction_date' => $invDate,
                            'transaction_datetime' => $invDate." ".date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN,
                            'coa_id' => $coaUtangUsahaBelumRealisasi,
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => $rec->id,
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $invNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => $totalHpRec,
                            'kredit' => 0,
                            'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian',
                        );
                        $jurnalTransaksiRepo->create($arrJurnalDebet);
                    }
                }
            }

            if(count($arrTax) > 0){
                foreach ($arrTax as $val){
                    if($val['posisi'] == 'debet'){
                        $namaDetail = "";
                        if(!empty($val['nama_item'])){
                            $namaDetail = ' dengan nama item '.$val['nama_item'];
                        }
                        else {
                            if(!empty($find->vendor)){
                                $namaDetail = ' dengan nama supplier '.$find->vendor->vendor_company_name ;
                            }
                        }
                        $arrJurnalDebet = array(
                            'transaction_date' => $invDate,
                            'transaction_datetime' => $invDate." ".date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN,
                            'coa_id' => $val['coa_id'],
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => $val['id_item'],
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $invNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => $val['nominal'],
                            'kredit' => 0,
                            'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian'.$namaDetail,
                        );
                        $jurnalTransaksiRepo->create($arrJurnalDebet);
                    } else
                    {
                        $arrJurnalKredit = array(
                            'transaction_date' => $invDate,
                            'transaction_datetime' => $invDate." ".date('H:i:s'),
                            'created_by' => $find->created_by,
                            'updated_by' => $find->created_by,
                            'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN,
                            'coa_id' => $val['coa_id'],
                            'transaction_id' => $find->id,
                            'transaction_sub_id' => $val['id_item'],
                            'created_at' => date("Y-m-d H:i:s"),
                            'updated_at' => date("Y-m-d H:i:s"),
                            'transaction_no' => $invNo,
                            'transaction_status' => JurnalStatusEnum::OK,
                            'debet' => 0,
                            'kredit' => $val['nominal'],
                            'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian'.$namaDetail,
                        );
                        $jurnalTransaksiRepo->create($arrJurnalKredit);
                    }
                }
            }

            //jurnal diskon
            if(!empty($find->discount_total)) {
                $arrJurnalDebet = array(
                    'transaction_date' => $invDate,
                    'transaction_datetime' => $invDate." ".date('H:i:s'),
                    'created_by' => $find->created_by,
                    'updated_by' => $find->created_by,
                    'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN,
                    'coa_id' => $coaUtangUsaha,
                    'transaction_id' => $find->id,
                    'transaction_sub_id' => 0,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_no' => $invNo,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'debet' => $find->discount_total,
                    'kredit' => 0,
                    'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian',
                );
                $jurnalTransaksiRepo->create($arrJurnalDebet);
                $arrJurnalKredit = array(
                    'transaction_date' => $invDate,
                    'transaction_datetime' => $invDate." ".date('H:i:s'),
                    'created_by' => $find->created_by,
                    'updated_by' => $find->created_by,
                    'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN,
                    'coa_id' => $coaPotongan,
                    'transaction_id' => $find->id,
                    'transaction_sub_id' => $val['id_item'],
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_no' => $invNo,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'debet' => 0,
                    'kredit' => $find->discount_total,
                    'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian'.$namaDetail,
                );
                $jurnalTransaksiRepo->create($arrJurnalKredit);
            }

            //jurnal pembalik uang muka
            $totalNominalUangMuka = 0;
            $findDp = PurchaseInvoicingDp::where(array('invoice_id' => $find->id))->with(['downpayment','downpayment.tax','downpayment.tax.taxgroup','downpayment.tax.taxgroup.tax'])->get();
            if(!empty($findDp)){
                foreach ($findDp as $dp){
                    $uangMuka = $dp->downpayment;
                    $nominalUangMuka = $uangMuka->nominal;
                    $arrTaxUangMuka = array();
                    $dpp =$nominalUangMuka;
                    $totalNominalUangMuka = $totalNominalUangMuka + $nominalUangMuka;
                    if($uangMuka->faktur_accepted == TypeEnum::FAKTUR_ACCEPTED){
                        if(!empty($uangMuka->tax_id)){
                            $objTax = $uangMuka->tax;
                            if(!empty($objTax)) {
                                if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
                                    $getCalcTax = Helpers::hitungIncludeTax($objTax->tax_percentage,$nominalUangMuka);
                                    $ppn = $getCalcTax[TypeEnum::PPN];
                                    $posisi = "kredit";
                                    if ($objTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                                        $posisi = "debet";
                                        $dpp = $dpp + $ppn;
                                    } else {
                                        $dpp = $dpp - $ppn;
                                    }
                                    $arrTaxUangMuka[] = array(
                                        'coa_id' => $objTax->purchase_coa_id,
                                        'posisi' => $posisi,
                                        'nominal' => $ppn,
                                        'id_item' => $uangMuka->id
                                    );
                                }
                                else {
                                    $tagGroups = $objTax->taxgroup;
                                    if (!empty($tagGroups)) {
                                        $total = $nominalUangMuka;
                                        foreach ($tagGroups as $group) {
                                            $findTax = $group->tax;
                                            if (!empty($findTax)) {
                                                $pembagi = ($findTax->tax_percentage + 100) / 100;
                                                $subtotal = $total / $pembagi;
                                                $tax = ($findTax->tax_percentage / 100) * $subtotal;
                                                $posisi = "kredit";
                                                if ($findTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                                                    $posisi = "debet";
                                                    $dpp = $dpp + $tax;
                                                } else {
                                                    $dpp = $dpp - $tax;
                                                }
                                                $arrTaxUangMuka[] = array(
                                                    'coa_id' => $findTax->purchase_coa_id,
                                                    'posisi' => $posisi,
                                                    'nominal' => $tax,
                                                    'id_item' => $uangMuka->id
                                                );
                                            }
                                        }
                                    }
                                }
                            }
                        }

                    }
                    $arrJurnalDebet = array(
                        'transaction_date' => $invDate,
                        'transaction_datetime' => $invDate." ".date('H:i:s'),
                        'created_by' => $find->created_by,
                        'updated_by' => $find->created_by,
                        'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN,
                        'coa_id' => $coaUtangUsaha,
                        'transaction_id' => $find->id,
                        'transaction_sub_id' => 0,
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                        'transaction_no' => $invNo,
                        'transaction_status' => JurnalStatusEnum::OK,
                        'debet' => $nominalUangMuka,
                        'kredit' => 0,
                        'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian',
                    );
                    $jurnalTransaksiRepo->create($arrJurnalDebet);
                    if(!empty($arrTaxUangMuka)){
                        if(count($arrTaxUangMuka) > 0){
                            foreach ($arrTaxUangMuka as $val){
                                if($val['posisi'] == 'debet'){
                                    $arrJurnalDebet = array(
                                        'transaction_date' => $invDate,
                                        'transaction_datetime' => $invDate." ".date('H:i:s'),
                                        'created_by' => $find->created_by,
                                        'updated_by' => $find->created_by,
                                        'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN,
                                        'coa_id' => $val['coa_id'],
                                        'transaction_id' => $find->id,
                                        'transaction_sub_id' => 0,
                                        'created_at' => date("Y-m-d H:i:s"),
                                        'updated_at' => date("Y-m-d H:i:s"),
                                        'transaction_no' => $invNo,
                                        'transaction_status' => JurnalStatusEnum::OK,
                                        'debet' => $val['nominal'],
                                        'kredit' => 0,
                                        'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian',
                                    );

                                    $jurnalTransaksiRepo->create($arrJurnalDebet);
                                } else
                                {
                                    $arrJurnalKredit = array(
                                        'transaction_date' => $invDate,
                                        'transaction_datetime' => $invDate." ".date('H:i:s'),
                                        'created_by' => $find->created_by,
                                        'updated_by' => $find->created_by,
                                        'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN,
                                        'coa_id' => $val['coa_id'],
                                        'transaction_id' => $find->id,
                                        'transaction_sub_id' => 0,
                                        'created_at' => date("Y-m-d H:i:s"),
                                        'updated_at' => date("Y-m-d H:i:s"),
                                        'transaction_no' => $invNo,
                                        'transaction_status' => JurnalStatusEnum::OK,
                                        'debet' => 0,
                                        'kredit' => $val['nominal'],
                                        'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian',
                                    );
                                    $jurnalTransaksiRepo->create($arrJurnalKredit);
                                }
                            }
                        }

                    }

                    $arrJurnalKredit = array(
                        'transaction_date' => $invDate,
                        'transaction_datetime' => $invDate." ".date('H:i:s'),
                        'created_by' => $find->created_by,
                        'updated_by' => $find->created_by,
                        'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN,
                        'coa_id' => $coaUangMuka,
                        'transaction_id' => $find->id,
                        'transaction_sub_id' => 0,
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                        'transaction_no' => $invNo,
                        'transaction_status' => JurnalStatusEnum::OK,
                        'debet' => 0,
                        'kredit' => $dpp,
                        'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian',
                    );
                    $jurnalTransaksiRepo->create($arrJurnalKredit);
                    DpRepo::changeStatusUangMuka($uangMuka->id);
                }
            }
            $totalUtangUsaha = $find->grandtotal+$totalNominalUangMuka+$find->discount_total;
            $arrJurnalKredit = array(
                'transaction_date' => $invDate,
                'transaction_datetime' => $invDate." ".date('H:i:s'),
                'created_by' => $find->created_by,
                'updated_by' => $find->created_by,
                'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN,
                'coa_id' => $coaUtangUsaha,
                'transaction_id' => $find->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $invNo,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => 0,
                'kredit' => $totalUtangUsaha,
                'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian',
            );
            $jurnalTransaksiRepo->create($arrJurnalKredit);
            $arrUpdateCoaUtangUsaha = array(
                'coa_id' => $coaUtangUsaha
            );
            $this->update($arrUpdateCoaUtangUsaha, $find->id);
        }
    }

    public function getTransaksi($idInvoice): array
    {
        $arrTransaksi = array();
        $find = $this->findOne($idInvoice, array(),['payment','payment.purchasepayment']);
        if(!empty($find)){
            if(!empty($find->payment)){
                foreach ($find->payment as $val){
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
        return $arrTransaksi;
    }

    public function getPaymentList($idInvoice){
        $findPayment = PurchasePaymentInvoice::where(array('invoice_id' => $idInvoice))->orderBy('payment_date','DESC')->with(['purchasepayment','retur','jurnal'])->get();
        return $findPayment;
    }

    public function getDpListBy($idInvoice){
        $findDp = PurchaseInvoicingDp::where(array('invoice_id' => $idInvoice))->with(['downpayment'])->get();
        $arrDp = array();
        if(!empty($findDp)){
            foreach ($findDp as $dp){
                $arrDp[] = $dp->downpayment;
            }
        }
        return $arrDp;
    }

    public static function insertIntoPaymentFromRetur($idInvoice,$returId,$returDate,$total){
        if(!empty($idInvoice)){
            $findInvoice = (new self(new PurchaseInvoicing()))->findOne($idInvoice);
            if(!empty($findInvoice)){
                if($findInvoice->invoice_status == StatusEnum::BELUM_LUNAS){
                    $arrInvoice = array(
                        'invoice_no' => $findInvoice->invoice_no,
                        'total_payment' => Utility::remove_commas($total),
                        'payment_date' => $returDate,
                        'total_discount' => '0',
                        'coa_id_discount' => "",
                        'invoice_id' => $findInvoice->id,
                        'payment_id' => '0',
                        'jurnal_id' => 0,
                        'vendor_id' => $findInvoice->vendor_id,
                        'retur_id' => $returId,
                        'total_overpayment' => 0,
                        'coa_id_overpayment' => ""
                    );
                    PurchasePaymentInvoice::create($arrInvoice);
                }

            }
        }
    }

    public static function getStatusInvoice($idInvoice){
        $find = (new self(new PurchaseInvoicing()))->findOne($idInvoice);
        if(!empty($find)) {
            return $find->invoice_status;
        } else {
            return "";
        }
    }

    public static function changeStatusInvoice($idInvoice): void
    {
        $invoiceRepo = (new self(new PurchaseInvoicing()));
        $paymentInvoiceRepo = new PaymentInvoiceRepo(new PurchasePaymentInvoice());
        $findInvoice = $invoiceRepo->findOne($idInvoice);
        $paid = $paymentInvoiceRepo->getAllPaymentByInvoiceId($idInvoice);
        if($paid == $findInvoice->grandtotal) {
            $invoiceRepo->update(array('invoice_status' => StatusEnum::LUNAS), $idInvoice);
        }
    }

    public function getArrDataProduct($invProduct, $coaSediaan, $coaBiayaId)
    {
        $arrCoaProduct = array();
        if(count($invProduct) > 0){
            foreach ($invProduct as $key => $item){
                $product = $item->product;
                $objTax = $item->tax;
                $hpp = $item->price;
                $subtotal = $item->subtotal;
                $tax = 0;
                if(!empty($objTax)) {
                    if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
                        $posisi = "debet";
                        if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE) {
                            $pembagi = ($item->tax_percentage + 100) / 100;
                            $dpp = $item->subtotal / $pembagi;
                            $subtotal = $dpp;
                            $tax = ($item->tax_percentage / 100) * $dpp;

                        } else {
                            $tax = ($item->tax_percentage / 100) * $subtotal;
                        }

                        if ($objTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                            $posisi = "kredit";
                        }
                        $arrTax[] = array(
                            'coa_id' => $objTax->purchase_coa_id,
                            'posisi' => $posisi,
                            'nominal' => $tax,
                            'nama_item' => $product->item_name,
                            'id_item' => $item->id
                        );
                    } else {
                        $tagGroups = $objTax->taxgroup;
                        if (!empty($tagGroups)) {
                            $total = $subtotal;
                            foreach ($tagGroups as $group) {
                                $findTax = Tax::where(array('id' => $group))->first();
                                if (!empty($findTax)) {
                                    if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE) {
                                        $pembagi = ($findTax->tax_percentage + 100) / 100;
                                        $subtotal = $total / $pembagi;
                                    }
                                    $tax = ($findTax->tax_percentage / 100) * $subtotal;
                                    $posisi = "debet";
                                    if ($findTax->tax_sign == VarType::TAX_SIGN_PEMOTONG) {
                                        $posisi = "kredit";
                                    }
                                    $arrTax[] = array(
                                        'coa_id' => $findTax->purchase_coa_id,
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

                if(!empty($product)){
                    $coaSediaan = !empty($product->coa_id) ? $product->coa_id : $product->coa_biaya_id;
                    $arrCoaProduct[] = array(
                        'coa_id' => $coaSediaan,
                        'nominal' => $subtotal,
                        'nama_item' => $product->item_name,
                        'id_item' => $item->id
                    );
                } else {
                    $arrCoaProduct[] = array(
                        'coa_id' => $coaBiayaId,
                        'nominal' => $subtotal,
                        'nama_item' => $item->service_name,
                        'id_item' => $item->id
                    );
                }
            }
        }
        return $arrCoaProduct;
    }

    public static function getTotalInvoiceBySaldoAwalCoaId($coaId)
    {
        $getTotal = PurchaseInvoicing::where(array('coa_id' => $coaId, 'input_type' => InputType::SALDO_AWAL))->sum('grandtotal');
        return $getTotal;
    }

    public static function sumGrandTotalByVendor($vendorId, $dari, $sampai='', $sign='between'){
        if($sign == 'between') {
            $total = PurchaseInvoicing::where([['vendor_id', '=', $vendorId]])->whereBetween('invoice_date',[$dari,$sampai])->sum('grandtotal');
        } else{
            $total = PurchaseInvoicing::where([['invoice_date', $sign, $dari], ['vendor_id', '=', $vendorId]])->sum('grandtotal');
        }
        return $total;
    }
}
