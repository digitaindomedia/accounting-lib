<?php

namespace Icso\Accounting\Repositories\Penjualan\Invoice;

use Exception;
use Icso\Accounting\Enums\InvoiceStatusEnum;
use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Master\PaymentMethod;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicingDelivery;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicingDp;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicingMeta;
use Icso\Accounting\Models\Penjualan\Order\SalesOrderProduct;
use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPayment;
use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPaymentInvoice;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Icso\Accounting\Models\Penjualan\UangMuka\SalesDownpayment;
use Icso\Accounting\Models\Persediaan\Inventory;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Penjualan\Delivery\DeliveryRepo;
use Icso\Accounting\Repositories\Penjualan\Downpayment\DpRepo;
use Icso\Accounting\Repositories\Penjualan\Order\SalesOrderRepo;
use Icso\Accounting\Repositories\Penjualan\Payment\PaymentInvoiceRepo;
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

    public function __construct(SalesInvoicing $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        $model = new $this->model;
        return $model->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    if($metod == 'whereBetween'){
                        $que->$metod($item['value']['field'],$item['value']['value']);
                    } else {
                        $que->$metod($item['value']);
                    }
                }
            });
        })->when(!empty($search), function ($query) use($search){
            $query->where('invoice_no', 'like', '%' .$search. '%')
                ->orWhereHas('vendor', function ($query) use($search) {
                    $query->where('vendor_name', 'like', '%' .$search. '%')
                        ->orWhere('vendor_company_name', 'like', '%' .$search. '%');
                })
                ->orWhereHas('order', function ($query) use($search) {
                    $query->where('order_no', 'like', '%' .$search. '%');
                });
        })->orderBy('invoice_date','desc')
            ->with(['vendor','order','invoicedelivery.delivery.deliveryproduct.product'])
            ->offset($page)->limit($perpage)->get();
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        $model = new $this->model;
        return $model->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    if($metod == 'whereBetween'){
                        $que->$metod($item['value']['field'],$item['value']['value']);
                    } else {
                        $que->$metod($item['value']);
                    }
                }
            });
        })->when(!empty($search), function ($query) use($search){
            $query->where('invoice_no', 'like', '%' .$search. '%')
                ->orWhereHas('vendor', function ($query) use($search) {
                    $query->where('vendor_name', 'like', '%' .$search. '%')
                        ->orWhere('vendor_company_name', 'like', '%' .$search. '%');
                });
        })->count();
    }

    /**
     * Store method with strict Transaction and Balance Check
     */
    public function store(Request $request, array $other = [])
    {
        $id = $request->id;
        $userId = $request->user_id;

        // Prepare Data
        $arrData = $this->gatherInputData($request);
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::beginTransaction();
        try {
            // 1. Create or Update Header
            if (empty($id)) {
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $arrData['created_by'] = $userId;
                $arrData['reason'] = '';
                $arrData['invoice_status'] = ($request->input_type == InputType::POS) ? InvoiceStatusEnum::LUNAS : InvoiceStatusEnum::BELUM_LUNAS;
                $res = $this->create($arrData);
                $invoiceId = $res->id;
            } else {
                $this->update($arrData, $id);
                $invoiceId = $id;
                $this->deleteAdditional($invoiceId);
            }

            // 2. Process Invoice Items
            // If creating from delivery and no invoice items are provided, create them from the delivery items.
            if (!empty($request->delivery) && empty($request->orderproduct)) {
                $this->createInvoiceItemsFromDelivery($request, $invoiceId);
            } else {
                // Original logic for direct sales or POS
                $this->processOrderProducts($request, $invoiceId);
            }

            // 3. Process Other Linked Data
            $this->processDp($request, $invoiceId);
            $this->processDelivery($request, $invoiceId);

            // 4. Handle POS specific logic or Regular Posting
            if ($request->input_type == InputType::POS) {
                $this->processPOSInvoice($request, $invoiceId, $arrData['invoice_no']);
            } else {
                // Regular Posting (Journal & Inventory)
                // This will THROW Exception if Unbalanced
                $this->postingJurnal($invoiceId);
            }

            // 5. File Upload
            $this->handleFileUploads($request, $invoiceId);

            DB::commit();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            Log::error("Sales Invoice Store Error: " . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            return false;
        }
    }

    private function gatherInputData(Request $request)
    {
        $invoiceNo = $request->invoice_no ?: self::generateCodeTransaction(new SalesInvoicing(), KeyNomor::NO_INVOICE_PENJUALAN, 'invoice_no', 'invoice_date');
        $vendorId = !empty($request->vendor_id) ? $request->vendor_id : SettingRepo::getDefaultCustomer();
        $warehouseId = !empty($request->warehouse_id) ? $request->warehouse_id : SettingRepo::getDefaultWarehouse();

        return [
            'invoice_date'  => $request->invoice_date ? Utility::changeDateFormat($request->invoice_date) : date('Y-m-d'),
            'invoice_no'    => $invoiceNo,
            'note'          => $request->note ?? '',
            'due_date'      => $request->due_date ? $request->due_date : date('Y-m-d'),
            'updated_by'    => $request->user_id,
            'updated_at'    => date('Y-m-d H:i:s'),
            'vendor_id'     => $vendorId,
            'tax_type'      => $request->tax_type ?? '',
            'discount_type' => $request->discount_type ?? '',
            'invoice_type'  => $request->invoice_type,
            'input_type'    => $request->input_type,
            'order_id'      => !empty($request->order_id) ? $request->order_id : 0,
            'dp_nominal'    => $request->dp_nominal ? Utility::remove_commas($request->dp_nominal) : 0,
            'subtotal'      => Utility::remove_commas($request->subtotal),
            'dpp_total'     => Utility::remove_commas($request->dpp_total ?? 0),
            'discount'      => Utility::remove_commas($request->discount ?? 0),
            'discount_total'=> Utility::remove_commas($request->discount_total ?? 0),
            'tax_total'     => Utility::remove_commas($request->tax_total ?? 0),
            'grandtotal'    => Utility::remove_commas($request->grandtotal),
            'coa_id'        => $request->coa_id ?? 0,
            'warehouse_id'  => $warehouseId,
            'jurnal_id'     => $request->jurnal_id ?? 0
        ];
    }

    private function createInvoiceItemsFromDelivery(Request $request, $invoiceId)
    {
        Log::info("Attempting to create invoice items from delivery for Invoice ID: $invoiceId");
        $deliveryIds = collect(is_array($request->delivery) ? $request->delivery : json_decode(json_encode($request->delivery)))->pluck('id');
        $deliveries = SalesDelivery::with(['deliveryproduct.orderproduct'])->find($deliveryIds);

        if ($deliveries->isEmpty()) {
            Log::warning("In createInvoiceItemsFromDelivery: No valid deliveries found for the provided IDs.");
            return;
        }

        foreach ($deliveries as $delivery) {
            Log::info("In createInvoiceItemsFromDelivery: Processing Delivery ID: {$delivery->id}");
            if ($delivery->deliveryproduct->isEmpty()) {
                Log::warning("In createInvoiceItemsFromDelivery: Delivery ID: {$delivery->id} has no delivery products.");
                continue;
            }
            foreach ($delivery->deliveryproduct as $deliveryProduct) {
                $orderProduct = $deliveryProduct->orderproduct;
                if (!$orderProduct) {
                    Log::warning("In createInvoiceItemsFromDelivery: Could not find related SalesOrderProduct for SalesDeliveryProduct ID: {$deliveryProduct->id} (order_product_id: {$deliveryProduct->order_product_id}). Skipping item creation for invoice.");
                    continue;
                }

                Log::info("In createInvoiceItemsFromDelivery: Found OrderProduct ID: {$orderProduct->id} for DeliveryProduct ID: {$deliveryProduct->id}. Creating invoice item.");

                $pricePerUnit = ($orderProduct->qty > 0) ? ($orderProduct->subtotal / $orderProduct->qty) : 0;
                $newSubtotal = $pricePerUnit * $deliveryProduct->qty;

                SalesOrderProduct::create([
                    'qty'               => $deliveryProduct->qty,
                    'qty_left'          => $deliveryProduct->qty,
                    'product_id'        => $orderProduct->product_id,
                    'unit_id'           => $orderProduct->unit_id,
                    'tax_id'            => $orderProduct->tax_id,
                    'tax_percentage'    => $orderProduct->tax_percentage,
                    'price'             => $orderProduct->price,
                    'tax_type'          => $orderProduct->tax_type,
                    'discount_type'     => $orderProduct->discount_type,
                    'discount'          => $orderProduct->discount,
                    'subtotal'          => $newSubtotal,
                    'multi_unit'        => $orderProduct->multi_unit,
                    'order_id'          => 0,
                    'invoice_id'        => $invoiceId
                ]);
            }
        }
    }

    private function processOrderProducts(Request $request, $idInvoice)
    {
        $products = is_array($request->orderproduct) ? $request->orderproduct : json_decode(json_encode($request->orderproduct));
        if (!empty($products)) {
            foreach ($products as $item) {
                $item = (object)$item;
                SalesOrderProduct::create([
                    'qty'               => $item->qty,
                    'qty_left'          => $item->qty,
                    'product_id'        => $item->product_id ?? 0,
                    'unit_id'           => $item->unit_id ?? 0,
                    'tax_id'            => $item->tax_id ?? 0,
                    'tax_percentage'    => $item->tax_percentage ?? 0,
                    'price'             => Utility::remove_commas($item->price ?? 0),
                    'tax_type'          => $request->tax_type ?? '',
                    'discount_type'     => $item->discount_type ?? '',
                    'discount'          => Utility::remove_commas($item->discount ?? 0),
                    'subtotal'          => Utility::remove_commas($item->subtotal ?? 0),
                    'multi_unit'        => 0,
                    'order_id'          => 0,
                    'invoice_id'        => $idInvoice
                ]);
            }
        }
    }

    private function processDp(Request $request, $idInvoice)
    {
        if (!empty($request->dp)) {
            $dps = is_array($request->dp) ? $request->dp : json_decode(json_encode($request->dp));
            foreach ($dps as $dp) {
                $dp = (object)$dp;
                SalesInvoicingDp::create([
                    'invoice_id' => $idInvoice,
                    'dp_id' => $dp->id
                ]);
            }
        }
    }

    private function processDelivery(Request $request, $idInvoice)
    {
        if (!empty($request->delivery)) {
            $deliveries = is_array($request->delivery) ? $request->delivery : json_decode(json_encode($request->delivery));
            foreach ($deliveries as $item) {
                $item = (object)$item;
                SalesInvoicingDelivery::create([
                    'invoice_id' => $idInvoice,
                    'delivery_id' => $item->id
                ]);
                SalesOrderRepo::closeStatusOrderById($item->order_id);
            }
        }
    }

    private function processPOSInvoice(Request $request, $idInvoice, $noInvoice)
    {
        SalesInvoicingMeta::create([
            'invoice_id' => $idInvoice,
            'meta_key' => 'payment',
            'meta_value' => Utility::remove_commas($request->total_payment)
        ]);
        $this->insertPayment($request, $idInvoice, $noInvoice); // Passed header no directly
        $this->postingJurnalPOS($idInvoice, $request);
    }

    private function insertPayment($request, $idInvoice, $noInvoice){
        $paymentNo = $request->payment_no ?: self::generateCodeTransaction(new SalesPayment(),KeyNomor::NO_PELUNASAN_PENJUALAN,'payment_no','payment_date');
        $paymentData = [
            'payment_date' => !empty($request->invoice_date) ? Utility::changeDateFormat($request->invoice_date) : date('Y-m-d'),
            'payment_no' => $paymentNo,
            'note' => "Pelunasan penjualan kasir",
            'total' => Utility::remove_commas($request->grandtotal),
            'vendor_id' => SettingRepo::getDefaultCustomer(),
            'payment_method_id' => $request->payment_method,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $request->user_id,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $request->user_id,
            'payment_status' => StatusEnum::SELESAI
        ];
        $res = SalesPayment::create($paymentData);

        SalesPaymentInvoice::create([
            'invoice_no' => $noInvoice,
            'total_payment' => Utility::remove_commas($request->grandtotal),
            'payment_date' => $paymentData['payment_date'],
            'invoice_id' => $idInvoice,
            'payment_id' => $res->id,
            'vendor_id' => SettingRepo::getDefaultCustomer(),
            'total_discount' => 0,
            'total_overpayment' => 0,
        ]);
    }

    public function deleteAdditional($id)
    {
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::INVOICE_PENJUALAN, $id);
        SalesInvoicingDp::where('invoice_id', $id)->delete();
        SalesInvoicingDelivery::where('invoice_id', $id)->delete();
        SalesOrderProduct::where('invoice_id', $id)->delete();
        SalesInvoicingMeta::where('invoice_id', $id)->delete();
        Inventory::where('transaction_code', TransactionsCode::INVOICE_PENJUALAN)->where('transaction_id', $id)->delete();
    }

    /**
     * Refactored Main Posting Jurnal
     * Handles Regular Invoices (Service, Direct Sale, From Delivery)
     */
    public function postingJurnal($idInvoice): void
    {
        // 1. Eager Load
        $find = $this->model->with([
            'vendor',
            'orderproduct.product',
            'orderproduct.tax.taxgroup.tax',
            'invoicedelivery.delivery.deliveryproduct.product',
        ])->find($idInvoice);

        if (!$find) return;

        // 2. Settings
        $settings = [
            'coa_piutang'   => SettingRepo::getOptionValue(SettingEnum::COA_PIUTANG_USAHA),
            'coa_penjualan' => SettingRepo::getOptionValue(SettingEnum::COA_PENJUALAN),
            'coa_sediaan'   => SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN),
            'coa_hpp'       => SettingRepo::getOptionValue(SettingEnum::COA_BEBAN_POKOK_PENJUALAN),
            'coa_transit'   => SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN_DALAM_PERJALANAN),
            'coa_potongan'  => SettingRepo::getOptionValue(SettingEnum::COA_POTONGAN_PENJUALAN),
            'coa_uang_muka' => SettingRepo::getOptionValue(SettingEnum::COA_UANG_MUKA_PENJUALAN),
            'coa_ppn_keluaran' => SettingRepo::getOptionValue(SettingEnum::COA_PPN_KELUARAN),
        ];

        $journalEntries = [];
        $inventoryRepo = new InventoryRepo(new Inventory());

        $hasInvoiceItems = !$find->orderproduct->isEmpty();
        $hasDelivery = !$find->invoicedelivery->isEmpty();

        // --- 1. REVENUE & TAX ---
        // Always generate from the invoice's own items, which have the correct pricing.
        if ($hasInvoiceItems) {
            foreach ($find->orderproduct as $item) {
                $coaRevenue = $settings['coa_penjualan'];
                $dpp = $item->subtotal;

                $taxes = $this->calculateTaxComponents($item, $item->subtotal);
                foreach ($taxes as $tax) {
                    $journalEntries[] = [
                        'coa_id' => $tax['coa_id'], 'posisi' => $tax['posisi'], 'nominal'=> $tax['nominal'],
                        'sub_id' => $item->id, 'note'   => 'Tax ' . ($item->product->item_name ?? '')
                    ];
                    if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE && $tax['posisi'] == 'kredit') {
                        $dpp -= $tax['nominal'];
                    }
                }

                if ($dpp != 0) {
                    $journalEntries[] = [
                        'coa_id' => $coaRevenue, 'posisi' => 'kredit', 'nominal'=> $dpp,
                        'sub_id' => $item->id, 'note'   => 'Penjualan ' . ($item->product->item_name ?? '')
                    ];
                }
            }
        } else {
            // Fallback to header totals if no items are on the invoice for some reason.
            Log::warning("InvoiceRepo: No items found on Invoice {$find->invoice_no}. Falling back to Header totals for revenue.");
            $taxTotal = $find->tax_total;
            $revenueTotal = $find->subtotal; // Subtotal should be DPP

            if ($revenueTotal > 0) {
                $journalEntries[] = [
                    'coa_id' => $settings['coa_penjualan'], 'posisi' => 'kredit', 'nominal'=> $revenueTotal,
                    'sub_id' => 0, 'note'   => 'Penjualan (Header Fallback)'
                ];
            }
            if ($taxTotal > 0) {
                if (empty($settings['coa_ppn_keluaran'])) {
                    throw new Exception("Sales Tax Account (COA_PPN_KELUARAN) is not configured in Settings, but the invoice has tax. Cannot create tax journal entry.");
                }
                $journalEntries[] = [
                    'coa_id' => $settings['coa_ppn_keluaran'], 'posisi' => 'kredit', 'nominal'=> $taxTotal,
                    'sub_id' => 0, 'note'   => 'Tax (Header Fallback)'
                ];
            }
        }

        // --- 2. COGS & INVENTORY ---
        if ($hasDelivery) {
             // From Delivery: Dr HPP, Cr In-Transit
             foreach ($find->invoicedelivery as $delInv) {
                 if (!$delInv->delivery) continue;
                 foreach ($delInv->delivery->deliveryproduct as $item) {
                    $hppFromDelivery = $item->hpp_price;
                    if($hppFromDelivery == 0) {
                        $log = Inventory::where(['transaction_code' => TransactionsCode::DELIVERY_ORDER, 'transaction_sub_id' => $item->id])->first();
                        $hppFromDelivery = $log ? $log->price : 0;
                    }
                    $subtotalHpp = $hppFromDelivery * $item->qty;

                    if ($subtotalHpp > 0) {
                        $journalEntries[] = [ 'coa_id' => $settings['coa_hpp'], 'posisi' => 'debet', 'nominal'=> $subtotalHpp, 'sub_id' => $item->id, 'note' => 'HPP (From Delivery)'];
                        $journalEntries[] = [ 'coa_id' => $settings['coa_transit'], 'posisi' => 'kredit', 'nominal'=> $subtotalHpp, 'sub_id' => $item->id, 'note' => 'Reversal In Transit'];
                    }
                 }
                 DeliveryRepo::changeStatusDelivery($delInv->delivery_id);
             }
        } elseif ($hasInvoiceItems) {
             // Direct Sale: Dr HPP, Cr Inventory
             foreach ($find->orderproduct as $item) {
                if ($find->invoice_type != ProductType::SERVICE && $item->product_id) {
                    $hpp = $inventoryRepo->movingAverageByDate($item->product_id, $item->unit_id, $find->invoice_date);
                    $subtotalHpp = $hpp * $item->qty;

                    if ($subtotalHpp > 0) {
                        $journalEntries[] = [ 'coa_id' => $settings['coa_hpp'], 'posisi' => 'debet', 'nominal'=> $subtotalHpp, 'sub_id' => $item->id, 'note' => 'HPP ' . ($item->product->item_name ?? '')];
                        $coaSediaan = !empty($item->product->coa_id) ? $item->product->coa_id : $settings['coa_sediaan'];
                        $journalEntries[] = [ 'coa_id' => $coaSediaan, 'posisi' => 'kredit', 'nominal'=> $subtotalHpp, 'sub_id' => $item->id, 'note' => 'Keluar Barang ' . ($item->product->item_name ?? '')];
                        $this->logInventory($find, $item, $hpp, $subtotalHpp, $inventoryRepo);
                    }
                }
             }
        }

        // --- 3. DISCOUNTS, DP, AR ---
        if ($find->discount_total > 0) {
            $journalEntries[] = [ 'coa_id' => $settings['coa_potongan'], 'posisi' => 'debet', 'nominal'=> $find->discount_total, 'sub_id' => 0, 'note' => 'Diskon Penjualan'];
            $journalEntries[] = [ 'coa_id' => $settings['coa_piutang'], 'posisi' => 'kredit', 'nominal'=> $find->discount_total, 'sub_id' => 0, 'note' => 'Pengurangan Piutang (Diskon)'];
        }

        $dpEntries = $this->calculateDpEntries($find->id, $settings['coa_uang_muka'], $settings['coa_piutang']);
        $journalEntries = array_merge($journalEntries, $dpEntries);

        $totalDpCredit = 0;
        foreach ($dpEntries as $dpEntry) {
             if (trim((string)$dpEntry['coa_id']) === trim((string)$settings['coa_piutang']) && strtolower($dpEntry['posisi']) == 'kredit') {
                 $totalDpCredit += $dpEntry['nominal'];
             }
        }
        $grossAR = $find->grandtotal + $totalDpCredit + $find->discount_total;
        $journalEntries[] = [ 'coa_id' => $settings['coa_piutang'], 'posisi' => 'debet', 'nominal'=> $grossAR, 'sub_id' => 0, 'note' => 'Piutang Usaha'];

        // 7. Update Invoice COA
        $this->update(['coa_id' => $settings['coa_piutang']], $find->id);

        // 8. Validate & Save
        Log::info(json_encode($journalEntries, JSON_PRETTY_PRINT));
        $this->validateAndSaveJournal($journalEntries, $find);
    }

    private function calculateTaxComponents($item, $amount): array
    {
        $results = [];
        if (empty($item) || !$item->relationLoaded('tax') || empty($item->tax)) {
            return $results;
        }
        $objTax = $item->tax;

        $taxList = [];
        if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
            $taxList[] = $objTax;
        } else {
            if ($objTax->relationLoaded('taxgroup')) {
                foreach ($objTax->taxgroup as $group) {
                    if ($group->relationLoaded('tax') && $group->tax) $taxList[] = $group->tax;
                }
            }
        }

        foreach ($taxList as $taxCfg) {
            $func = ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE)
                ? ($taxCfg->is_dpp_nilai_Lain == 1 ? 'hitungIncludeTaxDppNilaiLain' : 'hitungIncludeTax')
                : ($taxCfg->is_dpp_nilai_Lain == 1 ? 'hitungExcludeTaxDppNilaiLain' : 'hitungExcludeTax');

            $calc = Helpers::$func($taxCfg->tax_percentage, $amount);
            $taxNominal = $calc[TypeEnum::PPN];

            // Sales Tax Logic:
            // PEMOTONG (Withholding/PPh) -> Debit (Asset/Prepaid)
            // PENAMBAH (VAT/PPN) -> Kredit (Liability)
            $posisi = ($taxCfg->tax_sign == VarType::TAX_SIGN_PEMOTONG) ? 'debet' : 'kredit';

            $results[] = [
                'coa_id'  => $taxCfg->sales_coa_id,
                'posisi'  => $posisi,
                'nominal' => $taxNominal
            ];
        }
        return $results;
    }

    private function calculateDpEntries($invoiceId, $coaUangMuka, $coaPiutang)
    {
        $entries = [];
        $dps = SalesInvoicingDp::where('invoice_id', $invoiceId)->with(['downpayment.tax.taxgroup.tax'])->get();

        foreach ($dps as $row) {
            $dp = $row->downpayment;
            $nominal = $dp->nominal;
            $dpp = $nominal;

            // Handle DP Tax Reversal (Logic similar to Invoice Tax but reversed or adjusted)
            if ($dp->tax_id && $dp->tax) {
                $taxes = $this->calculateTaxComponents($dp, $nominal); // uses dp->tax
                foreach($taxes as $tax) {
                    $reversePos = ($tax['posisi'] == 'kredit') ? 'debet' : 'kredit';
                    $entries[] = [
                        'coa_id' => $tax['coa_id'],
                        'posisi' => $reversePos,
                        'nominal'=> $tax['nominal'],
                        'sub_id' => $dp->id,
                        'note'   => 'Reversal Tax DP'
                    ];

                    // Adjust DPP based on original logic logic
                    if ($tax['posisi'] == 'kredit') $dpp -= $tax['nominal'];
                    else $dpp += $tax['nominal'];
                }
            }

            // Debit Uang Muka (Close Liability)
            $entries[] = [
                'coa_id' => $coaUangMuka,
                'posisi' => 'debet',
                'nominal'=> $dpp,
                'sub_id' => $dp->id,
                'note'   => 'Penggunaan Uang Muka'
            ];

            // Credit Piutang (Reduce Invoice Claim)
            $entries[] = [
                'coa_id' => $coaPiutang,
                'posisi' => 'kredit',
                'nominal'=> $nominal,
                'sub_id' => $dp->id,
                'note'   => 'Potong DP ke Piutang'
            ];

            DpRepo::changeStatusUangMuka($dp->id);
        }
        return $entries;
    }

    private function logInventory($find, $item, $hpp, $subtotalHpp, $inventoryRepo)
    {
        $reqInventory = new Request();
        $reqInventory->coa_id = $item->product->coa_id ?? 0;
        $reqInventory->user_id = $find->created_by;
        $reqInventory->inventory_date = $find->invoice_date;
        $reqInventory->transaction_code = TransactionsCode::INVOICE_PENJUALAN;
        $reqInventory->transaction_id = $find->id;
        $reqInventory->transaction_sub_id = $item->id;
        $reqInventory->qty_out = $item->qty;
        $reqInventory->warehouse_id = $find->warehouse_id;
        $reqInventory->product_id = $item->product_id;
        $reqInventory->price = $hpp;
        $reqInventory->note = $find->note;
        $reqInventory->unit_id = $item->unit_id;
        $inventoryRepo->store($reqInventory);
    }

    private function validateAndSaveJournal(array $entries, $invoiceModel)
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $e) {
            if ($e['posisi'] == 'debet') $totalDebit += $e['nominal'];
            else $totalCredit += $e['nominal'];
        }

        if (abs($totalDebit - $totalCredit) > 1) {
            throw new Exception("Jurnal Sales Invoice {$invoiceModel->invoice_no} Tidak Balance! Debet: " . number_format($totalDebit) . ", Kredit: " . number_format($totalCredit));
        }

        $jurnalRepo = new JurnalTransaksiRepo(new JurnalTransaksi());

        foreach ($entries as $e) {
            if ($e['nominal'] == 0) continue;

            $jurnalRepo->create([
                'transaction_date'      => $invoiceModel->invoice_date,
                'transaction_datetime'  => $invoiceModel->invoice_date . " " . date('H:i:s'),
                'created_by'            => $invoiceModel->created_by,
                'updated_by'            => $invoiceModel->created_by,
                'transaction_code'      => TransactionsCode::INVOICE_PENJUALAN,
                'coa_id'                => $e['coa_id'],
                'transaction_id'        => $invoiceModel->id,
                'transaction_sub_id'    => $e['sub_id'],
                'transaction_no'        => $invoiceModel->invoice_no,
                'transaction_status'    => JurnalStatusEnum::OK,
                'debet'                 => ($e['posisi'] == 'debet') ? $e['nominal'] : 0,
                'kredit'                => ($e['posisi'] == 'kredit') ? $e['nominal'] : 0,
                'note'                  => $e['note'] ?? $invoiceModel->note,
                'created_at'            => date("Y-m-d H:i:s"),
                'updated_at'            => date("Y-m-d H:i:s"),
            ]);
        }
    }

    private function postingJurnalPOS($idInvoice, $request)
    {
        // Simple POS Journal: Debit Cash/Bank, Credit Sales
        $jurnalRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $find = SalesInvoicing::find($idInvoice);
        $paymentMethod = PaymentMethod::find($request->payment_method);

        if ($find && $paymentMethod) {
            $this->postingJurnal($idInvoice); // Do standard posting first for Revenue/COGS

            // Then record immediate payment (Cash In)
            // Debit Cash
            $jurnalRepo->create([
                'transaction_date' => $find->invoice_date,
                'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                'transaction_datetime'  => $find->invoice_date . " " . date('H:i:s'),
                'coa_id' => $paymentMethod->coa_id,
                'transaction_id' => $find->id,
                'transaction_no' => $find->invoice_no,
                'transaction_status'    => JurnalStatusEnum::OK,
                'transaction_sub_id'    => 0,
                'debet' => $find->grandtotal,
                'kredit' => 0,
                'created_by'            => $find->created_by,
                'updated_by'            => $find->created_by,
                'note' => 'POS Payment'
            ]);

            // Credit Piutang (Close the AR created by standard posting)
            $coaPiutang = SettingRepo::getOptionValue(SettingEnum::COA_PIUTANG_USAHA);
            $jurnalRepo->create([
                'transaction_date' => $find->invoice_date,
                'transaction_code' => TransactionsCode::INVOICE_PENJUALAN,
                'transaction_datetime'  => $find->invoice_date . " " . date('H:i:s'),
                'coa_id' => $coaPiutang,
                'transaction_id' => $find->id,
                'transaction_no' => $find->invoice_no,
                'transaction_status'    => JurnalStatusEnum::OK,
                'transaction_sub_id'    => 0,
                'debet' => 0,
                'kredit' => $find->grandtotal,
                'created_by'            => $find->created_by,
                'updated_by'            => $find->created_by,
                'note' => 'POS Payment Settlement'
            ]);
        }
    }

    private function handleFileUploads(Request $request, $idInvoice)
    {
        $fileUpload = new FileUploadService();
        $uploadedFiles = $request->file('files');
        if (!empty($uploadedFiles)) {
            foreach ($uploadedFiles as $file) {
                $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                if ($resUpload) {
                    SalesInvoicingMeta::create([
                        'invoice_id' => $idInvoice,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload
                    ]);
                }
            }
        }
    }

    public function getPaymentList($idInvoice){
        $findPayment = SalesPaymentInvoice::where(array('invoice_id' => $idInvoice))->orderBy('payment_date','DESC')->with(['salespayment','retur','jurnal'])->get();
        return $findPayment;
    }

    public function getDpListBy($idInvoice){
        $findDp = SalesInvoicingDp::where(array('invoice_id' => $idInvoice))->with(['downpayment'])->get();
        $arrDp = array();
        if(!empty($findDp)){
            foreach ($findDp as $dp){
                $arrDp[] = $dp->downpayment;
            }
        }
        return $arrDp;
    }

    public static function getStatusInvoice($idInvoice){
        $find = (new self(new SalesInvoicing()))->findOne($idInvoice);
        if(!empty($find)) {
            return $find->invoice_status;
        } else {
            return "";
        }
    }

    public static function changeStatusInvoice($idInvoice): void
    {
        $invoiceRepo = new self(new SalesInvoicing());
        $paymentInvoiceRepo = new PaymentInvoiceRepo(new SalesPaymentInvoice());
        $findInvoice = $invoiceRepo->findOne($idInvoice);
        
        if (!$findInvoice) return;

        $paid = $paymentInvoiceRepo->getAllPaymentByInvoiceId($idInvoice);
        if ($paid >= $findInvoice->grandtotal) {
            $invoiceRepo->update(['invoice_status' => StatusEnum::LUNAS], $idInvoice);
        } else {
            $invoiceRepo->update(['invoice_status' => StatusEnum::BELUM_LUNAS], $idInvoice);
        }
    }

    public static function getTotalInvoiceBySaldoAwalCoaId($coaId)
    {
        $getTotal = SalesInvoicing::where(array('coa_id' => $coaId, 'input_type' => InputType::SALDO_AWAL))->sum('grandtotal');
        return $getTotal;
    }

    public static function sumGrandTotalByVendor($vendorId, $dari, $sampai='', $sign='between'){
        if($sign == 'between') {
            $total = SalesInvoicing::where([['vendor_id', '=', $vendorId]])->whereBetween('invoice_date',[$dari,$sampai])->sum('grandtotal');
        } else{
            $total = SalesInvoicing::where([['invoice_date', $sign, $dari], ['vendor_id', '=', $vendorId]])->sum('grandtotal');
        }
        return $total;
    }
}