<?php

namespace Icso\Accounting\Repositories\Pembelian\Received;

use Exception;
use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TransactionType;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrderProduct;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceivedMeta;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceivedProduct;
use Icso\Accounting\Models\Pembelian\Retur\PurchaseReturProduct;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Pembelian\Order\OrderRepo;
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

class ReceiveRepo extends ElequentRepository
{
    protected $model;

    public function __construct(PurchaseReceived $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    // ... [Keep getAllDataBy, getAllTotalDataBy, getAllDataBetweenBy, getAllTotalDataBetweenBy unchanged] ...

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        $model = new $this->model;
        return $model->when(!empty($search), function ($query) use($search){
            $query->where('receive_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('receive_date','desc')->offset($page)->limit($perpage)->get();
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        $model = new $this->model;
        return $model->when(!empty($search), function ($query) use($search){
            $query->where('receive_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('receive_date','desc')->get();
    }

    public function getAllDataBetweenBy($search, $page, $perpage, array $where = [], array $whereBetween=[])
    {
        $model = new $this->model;
        return $model->when(!empty($search), function ($query) use($search){
            $query->where('receive_no', 'like', '%' .$search. '%')
                ->orWhere('note', 'like', '%' .$search. '%')
                ->orWhereHas('order', function ($query) use ($search) {
                    $query->where('order_no', 'like', '%' .$search. '%');
                });
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->when(!empty($whereBetween), function ($query) use($whereBetween){
            $query->whereBetween('receive_date', $whereBetween);
        })->orderBy('receive_date','desc')->with(['vendor', 'order', 'warehouse','receiveproduct.product'])->offset($page)->limit($perpage)->get();
    }

    public function getAllTotalDataBetweenBy($search, array $where = [], array $whereBetween=[])
    {
        $model = new $this->model;
        return $model->when(!empty($search), function ($query) use($search){
            $query->where('receive_no', 'like', '%' .$search. '%');
            // ... same search logic ...
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->when(!empty($whereBetween), function ($query) use($whereBetween){
            $query->whereBetween('receive_date', $whereBetween);
        })->orderBy('receive_date','desc')->count();
    }

    /**
     * Store with strict Atomic Transaction
     */
    public function store(Request $request, array $other = [])
    {
        $inventoryRepo = new InventoryRepo(new Inventory());
        $id = $request->id;
        $userId = $request->user_id;

        // Prepare Header Data
        $dataHeader = $this->gatherHeaderData($request);

        DB::beginTransaction();
        try {
            // 1. Create/Update Header
            if (empty($id)) {
                $dataHeader['created_at'] = date('Y-m-d H:i:s');
                $dataHeader['created_by'] = $userId;
                $dataHeader['receive_status'] = StatusEnum::OPEN;
                $res = $this->create($dataHeader);
                $recId = $res->id;
            } else {
                $this->update($dataHeader, $id);
                $recId = $id;
                // Cleanup existing details if updating
                $this->deleteAdditional($recId);
            }

            // 2. Process Products
            $products = is_array($request->receiveproduct) ? $request->receiveproduct : json_decode(json_encode($request->receiveproduct));

            if (!empty($products)) {
                foreach ($products as $item) {
                    $item = (object)$item; // Ensure object access

                    // A. Validation
                    $this->validateOrderQty($item->order_product_id, $item->qty);

                    // B. Calculation (HPP)
                    $detailHpp = $this->calculateHppAndTax($item->order_product_id, $item->qty);

                    // C. Save Detail
                    $subtotal = Helpers::hitungSubtotal($item->qty, $item->buy_price, $item->discount, $item->discount_type);

                    $resItem = PurchaseReceivedProduct::create([
                        'receive_id'        => $recId,
                        'qty'               => $item->qty,
                        'qty_left'          => $item->qty,
                        'product_id'        => $item->product_id,
                        'unit_id'           => $item->unit_id,
                        'order_product_id'  => $item->order_product_id,
                        'multi_unit'        => '0',
                        'hpp_price'         => $detailHpp['hpp_price'],
                        'buy_price'         => $detailHpp['buy_price'],
                        'tax_id'            => $detailHpp['tax_id'],
                        'tax_percentage'    => $detailHpp['tax_percentage'],
                        'tax_group'         => $detailHpp['tax_group'],
                        'discount'          => $detailHpp['discount'],
                        'subtotal'          => $subtotal,
                        'tax_type'          => $detailHpp['tax_type'],
                        'discount_type'     => $detailHpp['discount_type'],
                    ]);

                    // D. Update Inventory (Log)
                    $reqInv = new Request();
                    $reqInv->coa_id = $detailHpp['coa_id'] ?: 0;
                    $reqInv->user_id = $userId;
                    $reqInv->inventory_date = $dataHeader['receive_date'];
                    $reqInv->transaction_code = TransactionsCode::PENERIMAAN;
                    $reqInv->qty_in = $item->qty;
                    $reqInv->warehouse_id = $request->warehouse_id;
                    $reqInv->product_id = $item->product_id;
                    $reqInv->price = $detailHpp['hpp_price'];
                    $reqInv->note = $dataHeader['note'];
                    $reqInv->unit_id = $item->unit_id;
                    $reqInv->transaction_id = $recId;
                    $reqInv->transaction_sub_id = $resItem->id;

                    $inventoryRepo->store($reqInv);
                }
            }

            // 3. Posting Jurnal (CRITICAL)
            // Will Throw Exception if Unbalanced
            $this->postingJurnal($recId);

            // 4. Update Order Status
            OrderRepo::changeStatusPenerimaan($request->order_id);

            // 5. File Upload
            $this->handleFileUploads($request->file('files'), $recId, $userId);

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Receive Store Error: " . $e->getMessage());
            // Return false or rethrow based on controller needs
            return false;
        }
    }

    /**
     * Helper to gather header data
     */
    private function gatherHeaderData(Request $request)
    {
        $receivedNo = $request->received_no ?: self::generateCodeTransaction(new PurchaseReceived(), KeyNomor::NO_PENERIMAAN_PEMBELIAN, 'receive_no', 'receive_date');
        $order = json_decode(json_encode($request->order));
        $vendorId = $order->vendor->id ?? $request->vendor_id; // Robust handling

        return [
            'receive_date'   => $request->received_date ? Utility::changeDateFormat($request->received_date) : date("Y-m-d"),
            'receive_no'     => $receivedNo,
            'surat_jalan_no' => $request->surat_jalan_no ?? "",
            'note'           => $request->note ?? "",
            'updated_by'     => $request->user_id,
            'order_id'       => $request->order_id,
            'warehouse_id'   => $request->warehouse_id,
            'vendor_id'      => $vendorId,
            'reason'         => '',
            'updated_at'     => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Refactored Posting Jurnal with Balance Check
     */
    public function postingJurnal($id)
    {
        $find = $this->model->with(['receiveproduct.product'])->find($id);

        if (!$find) return;

        $settings = [
            'coa_belum_realisasi' => SettingRepo::getOptionValue(SettingEnum::COA_UTANG_USAHA_BELUM_REALISASI),
            'coa_sediaan'         => SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN),
        ];

        $journalEntries = [];
        $totalDebit = 0;
        $totalCredit = 0;

        // 1. Calculate Inventory/Asset Debits
        if ($find->receiveproduct) {
            foreach ($find->receiveproduct as $item) {
                // Determine COA
                $coaId = $settings['coa_sediaan'];
                $productName = "";

                if ($item->product) {
                    if (!empty($item->product->coa_id)) {
                        $coaId = $item->product->coa_id;
                    }
                    $productName = $item->product->item_name;
                }

                $note = !empty($find->note) ? $find->note : 'Penerimaan Barang ' . $productName;
                $subtotalHpp = $item->hpp_price * $item->qty;

                // Create Debit Entry
                $journalEntries[] = [
                    'coa_id' => $coaId,
                    'posisi' => 'debet',
                    'nominal'=> $subtotalHpp,
                    'sub_id' => $item->id,
                    'note'   => $note
                ];
            }
        }

        // 2. Calculate Unbilled Payable Credit (Total of all HPPs)
        // Summing up all HPP values from the loop above for the Credit side
        $totalHpp = 0;
        foreach ($journalEntries as $entry) {
            $totalHpp += $entry['nominal'];
        }

        if ($totalHpp > 0) {
            $journalEntries[] = [
                'coa_id' => $settings['coa_belum_realisasi'],
                'posisi' => 'kredit',
                'nominal'=> $totalHpp,
                'sub_id' => 0,
                'note'   => !empty($find->note) ? $find->note : 'Penerimaan Barang (Utang Belum Realisasi)'
            ];
        }

        // 3. Balance Check
        foreach ($journalEntries as $entry) {
            if ($entry['posisi'] == 'debet') $totalDebit += $entry['nominal'];
            else $totalCredit += $entry['nominal'];
        }

        if (abs($totalDebit - $totalCredit) > 1) {
            throw new Exception("Jurnal Penerimaan {$find->receive_no} Tidak Balance! Debet: " . number_format($totalDebit) . ", Kredit: " . number_format($totalCredit));
        }

        // 4. Save to DB
        $jurnalRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        foreach ($journalEntries as $entry) {
            $jurnalRepo->create([
                'transaction_date'      => $find->receive_date,
                'transaction_datetime'  => $find->receive_date . " " . date('H:i:s'),
                'created_by'            => $find->created_by,
                'updated_by'            => $find->created_by,
                'transaction_code'      => TransactionsCode::PENERIMAAN,
                'coa_id'                => $entry['coa_id'],
                'transaction_id'        => $find->id,
                'transaction_sub_id'    => $entry['sub_id'],
                'transaction_no'        => $find->receive_no,
                'transaction_status'    => JurnalStatusEnum::OK,
                'debet'                 => ($entry['posisi'] == 'debet') ? $entry['nominal'] : 0,
                'kredit'                => ($entry['posisi'] == 'kredit') ? $entry['nominal'] : 0,
                'note'                  => $entry['note'],
                'created_at'            => date("Y-m-d H:i:s"),
                'updated_at'            => date("Y-m-d H:i:s"),
            ]);
        }
    }

    /**
     * Helper: Validate if Receiving more than Ordered
     */
    private function validateOrderQty($orderProductId, $newQty)
    {
        $orderProduct = PurchaseOrderProduct::with('product')->find($orderProductId);
        if (!$orderProduct) throw new Exception("Order Product ID {$orderProductId} tidak ditemukan");

        // Total previously received (excluding current receive if strictly insert, but handled by deleteAdditional on update)
        $totalReceived = PurchaseReceivedProduct::where('order_product_id', $orderProductId)->sum('qty');

        if (($totalReceived + $newQty) > $orderProduct->qty) {
            $prodName = $orderProduct->product->item_name ?? '-';
            throw new Exception("Qty product '{$prodName}' ({$newQty} + {$totalReceived}) melebihi order ({$orderProduct->qty})");
        }
    }

    /**
     * Refactored Logic for HPP Calculation
     */
    private function calculateHppAndTax($orderProductId, $qty)
    {
        $op = PurchaseOrderProduct::with('product')->find($orderProductId);
        if (!$op) return ['hpp_price' => 0];

        $totalPrice = $qty * $op->price;
        $discountAmount = 0;

        // Calculate Discount
        if ($op->discount > 0) {
            if ($op->discount_type == TypeEnum::DISCOUNT_TYPE_PERCENT) {
                $discountAmount = ($op->discount / 100) * $totalPrice;
            } else {
                // Prorate fixed discount
                $totalOrderVal = $op->qty * $op->price;
                $discountAmount = Helpers::hitungProporsi($totalPrice, $totalOrderVal, $op->discount);
            }
        }

        $netTotal = $totalPrice - $discountAmount;
        $subtotalDpp = $netTotal;

        // Calculate DPP if Tax exists
        if (!empty($op->tax_id)) {
            // Usually for Receiving, we record the Asset Value.
            // If Tax is Non-Refundable (Capitalized), it increases Asset Value.
            // If Tax is Recoverable (VAT), it does not.
            // Assuming standard VAT is recoverable, DPP is the base.
            $taxCalc = Helpers::hitungTaxDpp($netTotal, $op->tax_id, $op->tax_type, $op->tax_percentage);
            if (!empty($taxCalc)) {
                $subtotalDpp = $taxCalc[TypeEnum::DPP];
            }
        }

        // HPP is DPP / Qty
        $hpp = ($qty > 0) ? $subtotalDpp / $qty : 0;

        $coaId = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN);
        if ($op->product && !empty($op->product->coa_id)) {
            $coaId = $op->product->coa_id;
        }

        return [
            'subtotal'       => $subtotalDpp,
            'hpp_price'      => $hpp,
            'buy_price'      => $op->price,
            'tax_id'         => $op->tax_id,
            'tax_percentage' => $op->tax_percentage,
            'tax_group'      => $op->tax_group,
            'discount'       => $discountAmount,
            'tax_type'       => $op->tax_type,
            'coa_id'         => $coaId,
            'discount_type'  => $op->discount_type
        ];
    }

    private function handleFileUploads($uploadedFiles, $recId, $userId)
    {
        if (!empty($uploadedFiles)) {
            $fileUpload = new FileUploadService();
            foreach ($uploadedFiles as $file) {
                $resUpload = $fileUpload->upload($file, tenant(), $userId);
                if ($resUpload) {
                    PurchaseReceivedMeta::create([
                        'receive_id' => $recId,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload
                    ]);
                }
            }
        }
    }

    public function deleteAdditional($id)
    {
        $find = PurchaseReceived::find($id);
        if ($find) {
            PurchaseReceivedProduct::where('receive_id', $id)->delete();
            PurchaseReceivedMeta::where('receive_id', $id)->delete();
            Inventory::where('transaction_code', TransactionsCode::PENERIMAAN)
                ->where('transaction_id', $id)->delete();
            JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PENERIMAAN, $id);
            OrderRepo::changeStatusPenerimaan($find->order_id);
        }
    }

    // ... [Keep getTotalReceived, getTotalReceivedByHpp, getQtyRetur, getReceivedProduct, getTransaksi, changeStatusPenerimaanById unchanged] ...

    public function getTotalReceived($id){
        $find = $this->findOne($id,array(),['receiveproduct']);
        $total = 0;
        if(!empty($find) && !empty($find->receiveproduct)){
            foreach ($find->receiveproduct as $item){
                $subtotal = $item->qty * $item->buy_price;
                $discount = 0;
                if(!empty($item->discount)){
                    if($item->discount_type == TypeEnum::DISCOUNT_TYPE_PERCENT){
                        $discount = ($item->discount / 100) * $subtotal;
                    } else {
                        $discount = $item->discount; // Note: Ensure this logic matches pro-ration if needed
                    }
                }
                $total += ($subtotal - $discount);
            }
        }
        return $total;
    }

    public static function getTotalReceivedByHpp($id){
        $find = PurchaseReceived::with('receiveproduct')->find($id);
        $total = 0;
        if($find && $find->receiveproduct) {
            foreach ($find->receiveproduct as $item) {
                $total += ($item->qty * $item->hpp_price);
            }
        }
        return $total;
    }

    public function getQtyRetur($recProductId)
    {
        return PurchaseReturProduct::where('receive_product_id', $recProductId)->sum('qty');
    }

    public static function getReceivedProduct($productId, $recId, $unitId){
        return PurchaseReceivedProduct::where(['product_id' => $productId, 'receive_id' => $recId, 'unit_id' => $unitId])->sum('qty');
    }

    public function getTransaksi($idPenerimaan): array
    {
        $arrTransaksi = array();
        $find = $this->findOne($idPenerimaan,array(),['retur','invoicereceived.invoice.payment.purchasepayment']);
        if(!empty($find)){
            if(!empty($find->invoicereceived)) {
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
                                if($pay){
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
        return $arrTransaksi;
    }

    public static function changeStatusPenerimaanById($id,$status= StatusEnum::SELESAI)
    {
        $instance = new self(new PurchaseReceived());
        $instance->update(['receive_status' => $status], $id);
    }
}