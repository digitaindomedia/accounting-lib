<?php

namespace Icso\Accounting\Repositories\Pembelian\Invoice;

use Exception;
use Icso\Accounting\Enums\InvoiceStatusEnum;
use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TransactionType;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\Tax;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicingDp;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicingMeta;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicingReceived;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrderProduct;
use Icso\Accounting\Models\Pembelian\Pembayaran\PurchasePaymentInvoice;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Pembelian\Downpayment\DpRepo;
use Icso\Accounting\Repositories\Pembelian\Order\OrderRepo;
use Icso\Accounting\Repositories\Pembelian\Payment\PaymentInvoiceRepo;
use Icso\Accounting\Repositories\Pembelian\Received\ReceiveRepo;
use Icso\Accounting\Repositories\Persediaan\Inventory\Interface\InventoryRepo;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\InputType;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Icso\Accounting\Utils\VarType;
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
        $model = new $this->model;
        return $model->when(!empty($search), function ($query) use($search){
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
        })->with(['vendor', 'invoicereceived','invoicereceived.receive.warehouse','invoicereceived.receive.receiveproduct','invoicereceived.receive.receiveproduct.unit','invoicereceived.receive.receiveproduct.product','invoicereceived.receive.receiveproduct.tax','invoicereceived.receive.receiveproduct.tax.taxgroup','invoicereceived.receive.receiveproduct.tax.taxgroup.tax','order','orderproduct', 'orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup','orderproduct.tax.taxgroup.tax','orderproduct.unit'])
            ->orderBy('invoice_date','desc')->offset($page)->limit($perpage)->get();
    }

    public function getAllTotalDataBy($search, array $where = []): int
    {
        $model = new $this->model;
        return $model->when(!empty($search), function ($query) use($search){
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
    }

    public function store(Request $request, array $other = []): bool
    {
        $inventoryRepo = new InventoryRepo(new Inventory());
        $userId = $request->user_id;
        $data = $this->gatherInputData($request);

        DB::beginTransaction();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $invoice = $this->saveInvoice($data, $request->id, $userId);

            if ($invoice) {
                $idInvoice = $request->id ?? $invoice->id;

                if(!empty($request->id)){
                    $this->deleteAdditional($request->id);
                }

                $this->handleOrderProducts($request->orderproduct, $idInvoice, $data['tax_type'], $data['invoice_date'], $data['note'], $userId, $data['warehouse_id'], $request->input_type, $inventoryRepo);
                $this->handleDownPayments($request->dp, $idInvoice);
                $this->handleReceivedProducts($request->receive, $idInvoice);

                if ($data['input_type'] == InputType::PURCHASE) {
                    $this->postingJurnal($idInvoice);
                }

                $this->handleFileUploads($request->file('files'), $idInvoice, $userId);

                DB::commit();
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                return true;
            } else {
                throw new Exception("Failed to save Invoice Header");
            }
        } catch (Exception $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            Log::error("Invoice Store Error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            return false;
        }
    }

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
            $this->update($data, $id);
            return $this->findOne($id);
        }
    }

    public function handleOrderProducts($orderProducts, string $invoiceId, string $taxType, string $invoiceDate, string $note, string $userId, string $warehouseId, string $inputType, InventoryRepo $inventoryRepo)
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

    public function saveOrderProduct($item, string $invoiceId, string $taxType, string $invoiceDate, string $note, string $userId, string $warehouseId,string $inputType, InventoryRepo $inventoryRepo)
    {
        $total = $item->subtotal ? Utility::remove_commas($item->subtotal) : 0;
        $hargaBeli = $item->price ? Utility::remove_commas($item->price) : 0;
        $findProduct = null;

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
            if($inputType == ProductType::ITEM && $findProduct) {
                if ($findProduct->product_type == ProductType::ITEM) {
                    if (!empty($item->tax_id) && $taxType == TypeEnum::TAX_TYPE_INCLUDE) {
                        $pembagi = ($item->tax_percentage + 100) / 100;
                        $subtotalHpp = $total / $pembagi;
                        $hpp = $subtotalHpp / $item->qty;
                    }
                    $this->addInventory($item, $invoiceDate, $note, $userId, $warehouseId, $inventoryRepo, $hpp, $invoiceId, $resItem->id);
                }
            }
        }
        return $resItem;
    }

    private function addInventory($item, string $invoiceDate, string $note, string $userId, string $warehouseId, InventoryRepo $inventoryRepo, float $hpp, string $invoiceId, string $resItemId)
    {
        $req = new Request();
        $findP = Product::find($item->product_id);
        $req->coa_id = $findP->coa_id ?? 0;
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

    public function handleDownPayments($dps, string $invoiceId)
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

    public function handleReceivedProducts($receives, string $invoiceId)
    {
        if (!empty($receives)) {
            $receives = json_decode(json_encode($receives));
            foreach ($receives as $item) {
                PurchaseInvoicingReceived::create([
                    'invoice_id' => $invoiceId,
                    'receive_id' => $item->id
                ]);
                OrderRepo::closeStatusOrderById($item->order_id);
            }
        }
    }

    private function handleFileUploads($uploadedFiles, string $invoiceId, string $userId)
    {
        if (!empty($uploadedFiles)) {
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
                if ($rec) $rec->update(['receive_status' => StatusEnum::OPEN]);
            }
        }
        $findInvoice = PurchaseInvoicing::find($id);
        if(!empty($findInvoice) && !empty($findInvoice->order_id)){
            OrderRepo::changeStatusPenerimaan($findInvoice->order_id);
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
        $find = $this->model->with([
            'vendor',
            'orderproduct.product',
            'orderproduct.tax',
            'orderproduct.tax.taxgroup.tax',
            'invoicereceived.receive.receiveproduct.product',
            'invoicereceived.receive.receiveproduct.tax',
            'invoicereceived.receive.receiveproduct.tax.taxgroup.tax'
        ])->find($idInvoice);

        if(!$find) return;

        $settings = [
            'coa_sediaan'       => SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN),
            'coa_utang_belum'   => SettingRepo::getOptionValue(SettingEnum::COA_UTANG_USAHA_BELUM_REALISASI),
            'coa_utang'         => SettingRepo::getOptionValue(SettingEnum::COA_UTANG_USAHA),
            'coa_potongan'      => SettingRepo::getOptionValue(SettingEnum::COA_POTONGAN_PEMBELIAN),
            'coa_uang_muka'     => SettingRepo::getOptionValue(SettingEnum::COA_UANG_MUKA_PEMBELIAN),
        ];

        $journalEntries = [];

        if ($find->invoicereceived->isEmpty()) {
            $items = $find->orderproduct;
            if ($items->isEmpty() && !empty($find->order_id)) {
                $items = PurchaseOrderProduct::where('order_id', $find->order_id)->with(['product','tax.taxgroup.tax'])->get();
            }

            foreach ($items as $item) {
                $coaDebit = $find->coa_id;
                if ($item->product) {
                    $coaDebit = $item->product->coa_id ?: $settings['coa_sediaan'];
                }

                $journalEntries[] = [
                    'coa_id' => $coaDebit,
                    'posisi' => 'debet',
                    'nominal'=> $item->subtotal,
                    'sub_id' => $item->id,
                    'note'   => $item->product ? $item->product->item_name : $item->service_name
                ];

                $taxes = $this->calculateTaxComponents($item, $item->subtotal);
                foreach($taxes as $tax) {
                    $journalEntries[] = [
                        'coa_id' => $tax['coa_id'],
                        'posisi' => $tax['posisi'],
                        'nominal'=> $tax['nominal'],
                        'sub_id' => $item->id,
                        'note'   => 'PPN'
                    ];
                }
            }
        }
        else {
            foreach ($find->invoicereceived as $recInv) {
                $receive = $recInv->receive;
                if(!$receive) continue;

                foreach($receive->receiveproduct as $itemRec) {
                    if (empty($itemRec->tax_type)) {
                        $itemRec->tax_type = $find->tax_type;
                    }

                    $taxes = $this->calculateTaxComponents($itemRec, $itemRec->subtotal);
                    foreach($taxes as $tax) {
                        $journalEntries[] = [
                            'coa_id' => $tax['coa_id'],
                            'posisi' => $tax['posisi'],
                            'nominal'=> $tax['nominal'],
                            'sub_id' => $itemRec->id,
                            'note'   => 'PPN Received'
                        ];
                    }
                }

                $totalHppRec = ReceiveRepo::getTotalReceivedByHpp($recInv->receive_id);

                $journalEntries[] = [
                    'coa_id' => $settings['coa_utang_belum'],
                    'posisi' => 'debet',
                    'nominal'=> $totalHppRec,
                    'sub_id' => $recInv->id,
                    'note'   => 'Reversal Utang Belum Realisasi'
                ];

                ReceiveRepo::changeStatusPenerimaanById($recInv->receive_id);
            }
        }

        if ($find->discount_total > 0) {
            $journalEntries[] = [
                'coa_id' => $settings['coa_utang'],
                'posisi' => 'debet',
                'nominal'=> $find->discount_total,
                'sub_id' => 0,
                'note'   => 'Diskon Invoice'
            ];
            $journalEntries[] = [
                'coa_id' => $settings['coa_potongan'],
                'posisi' => 'kredit',
                'nominal'=> $find->discount_total,
                'sub_id' => 0,
                'note'   => 'Potongan Pembelian'
            ];
        }

        $dpResult = $this->getDownPaymentEntries($find->id, $settings['coa_utang'], $settings['coa_uang_muka']);
        $journalEntries = array_merge($journalEntries, $dpResult['entries']);
        $totalDpNominal = $dpResult['total_nominal'];

        $totalUtang = $find->grandtotal + $totalDpNominal + $find->discount_total;

        $journalEntries[] = [
            'coa_id' => $settings['coa_utang'],
            'posisi' => 'kredit',
            'nominal'=> $totalUtang,
            'sub_id' => 0,
            'note'   => 'Utang Usaha'
        ];

        $this->update(['coa_id' => $settings['coa_utang']], $find->id);

        $this->validateAndSaveJournal($journalEntries, $find);
    }

    private function calculateTaxComponents($item, $amount): array
    {
        $results = [];
        $objTax = $item->tax;
        
        if (empty($objTax) && !empty($item->tax_id)) {
            $objTax = Tax::with(['taxgroup.tax'])->find($item->tax_id);
        }

        if (empty($objTax)) return $results;

        $taxList = [];
        if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
            $taxList[] = $objTax;
        } else {
            foreach ($objTax->taxgroup as $group) {
                if($group->tax) $taxList[] = $group->tax;
            }
        }

        foreach ($taxList as $taxCfg) {
            $calc = Helpers::hitungTaxDpp($amount, $taxCfg->id, $item->tax_type, $taxCfg->tax_percentage);
            
            if (is_array($calc)) {
                $taxNominal = $calc[TypeEnum::PPN];
                $sign = $calc[TypeEnum::TAX_SIGN] ?? $taxCfg->tax_sign;
                $posisi = ($sign == VarType::TAX_SIGN_PEMOTONG) ? 'kredit' : 'debet';

                $results[] = [
                    'coa_id'  => $taxCfg->purchase_coa_id,
                    'posisi'  => $posisi,
                    'nominal' => $taxNominal
                ];
            }
        }
        return $results;
    }

    private function getDownPaymentEntries($invoiceId, $coaUtang, $coaUangMuka): array
    {
        $entries = [];
        $totalNominal = 0;

        $dps = PurchaseInvoicingDp::where('invoice_id', $invoiceId)
            ->with(['downpayment.tax.taxgroup.tax'])->get();

        foreach ($dps as $row) {
            $dp = $row->downpayment;
            $nominal = $dp->nominal;
            $totalNominal += $nominal;
            $dpp = $nominal;

            $entries[] = [
                'coa_id' => $coaUtang,
                'posisi' => 'debet',
                'nominal'=> $nominal,
                'sub_id' => $dp->id,
                'note'   => 'Reversal DP'
            ];

            if ($dp->faktur_accepted == TypeEnum::FAKTUR_ACCEPTED && $dp->tax) {
                $taxes = $this->calculateTaxComponents($dp, $nominal);
                foreach($taxes as $tax){
                    if($tax['posisi'] == 'debet') {
                        $ppn = $tax['nominal'];
                        $isPemotong = ($tax['posisi'] == 'kredit');
                        $posisiRec = $isPemotong ? 'debet' : 'kredit';

                        if($isPemotong) $dpp += $ppn;
                        else $dpp -= $ppn;

                        $entries[] = [
                            'coa_id' => $tax['coa_id'],
                            'posisi' => $posisiRec,
                            'nominal'=> $ppn,
                            'sub_id' => $dp->id,
                            'note'   => 'Tax DP'
                        ];
                    }
                }
            }

            $entries[] = [
                'coa_id' => $coaUangMuka,
                'posisi' => 'kredit',
                'nominal'=> $dpp,
                'sub_id' => $dp->id,
                'note'   => 'Penggunaan Uang Muka'
            ];

            DpRepo::changeStatusUangMuka($dp->id);
        }

        return ['entries' => $entries, 'total_nominal' => $totalNominal];
    }

    private function validateAndSaveJournal(array $entries, $invoice)
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $e) {
            if ($e['posisi'] == 'debet') $totalDebit += $e['nominal'];
            else $totalCredit += $e['nominal'];
        }

        if (abs($totalDebit - $totalCredit) > 1) {
            throw new Exception("Jurnal Invoice {$invoice->invoice_no} Tidak Balance! Debet: " . number_format($totalDebit) . ", Kredit: " . number_format($totalCredit));
        }

        $jurnalRepo = new JurnalTransaksiRepo(new JurnalTransaksi());

        foreach ($entries as $e) {
            if ($e['nominal'] == 0) continue;

            $jurnalRepo->create([
                'transaction_date'      => $invoice->invoice_date,
                'transaction_datetime'  => $invoice->invoice_date . " " . date('H:i:s'),
                'created_by'            => $invoice->created_by,
                'updated_by'            => $invoice->created_by,
                'transaction_code'      => TransactionsCode::INVOICE_PEMBELIAN,
                'coa_id'                => $e['coa_id'],
                'transaction_id'        => $invoice->id,
                'transaction_sub_id'    => $e['sub_id'],
                'transaction_no'        => $invoice->invoice_no,
                'transaction_status'    => JurnalStatusEnum::OK,
                'debet'                 => ($e['posisi'] == 'debet') ? $e['nominal'] : 0,
                'kredit'                => ($e['posisi'] == 'kredit') ? $e['nominal'] : 0,
                'note'                  => $e['note'] ?? $invoice->note ?? 'Invoice Pembelian',
                'created_at'            => date("Y-m-d H:i:s"),
                'updated_at'            => date("Y-m-d H:i:s"),
            ]);
        }
    }

    public function getTransaksi($idInvoice): array
    {
        $arrTransaksi = array();
        $find = $this->findOne($idInvoice, array(),['payment','payment.purchasepayment']);
        if(!empty($find) && !empty($find->payment)){
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
        return $arrTransaksi;
    }

    public function getPaymentList($idInvoice){
        return PurchasePaymentInvoice::where(array('invoice_id' => $idInvoice))
            ->orderBy('payment_date','DESC')->with(['purchasepayment','retur','jurnal'])->get();
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
            $findInvoice = PurchaseInvoicing::find($idInvoice);
            if(!empty($findInvoice) && $findInvoice->invoice_status == StatusEnum::BELUM_LUNAS){
                PurchasePaymentInvoice::create([
                    'invoice_no' => $findInvoice->invoice_no,
                    'total_payment' => (float) Utility::remove_commas($total),
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
                ]);
            }
        }
    }

    public static function getStatusInvoice($idInvoice){
        $find = PurchaseInvoicing::find($idInvoice);
        return $find ? $find->invoice_status : "";
    }

    public static function changeStatusInvoice($idInvoice): void
    {
        $invoiceRepo = new self(new PurchaseInvoicing());
        $paymentInvoiceRepo = new PaymentInvoiceRepo(new PurchasePaymentInvoice());
        $findInvoice = $invoiceRepo->findOne($idInvoice);
        $paid = $paymentInvoiceRepo->getAllPaymentByInvoiceId($idInvoice);
        if($paid >= $findInvoice->grandtotal) {
            $invoiceRepo->update(array('invoice_status' => StatusEnum::LUNAS), $idInvoice);
        } else {
            $invoiceRepo->update(array('invoice_status' => StatusEnum::BELUM_LUNAS), $idInvoice);
        }
    }

    public static function getTotalInvoiceBySaldoAwalCoaId($coaId)
    {
        return PurchaseInvoicing::where(array('coa_id' => $coaId, 'input_type' => InputType::SALDO_AWAL))->sum('grandtotal');
    }

    public static function sumGrandTotalByVendor($vendorId, $dari, $sampai='', $sign='between'){
        if($sign == 'between') {
            return PurchaseInvoicing::where([['vendor_id', '=', $vendorId]])->whereBetween('invoice_date',[$dari,$sampai])->sum('grandtotal');
        } else{
            return PurchaseInvoicing::where([['invoice_date', $sign, $dari], ['vendor_id', '=', $vendorId]])->sum('grandtotal');
        }
    }
}
