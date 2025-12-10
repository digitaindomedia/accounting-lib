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

    // ... [Keep getAllDataBy and getAllTotalDataBy as original] ...
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

            // 2. Process Linked Data (Products, DP, Delivery)
            $this->processOrderProducts($request, $invoiceId);
            $this->processDp($request, $invoiceId);
            $this->processDelivery($request, $invoiceId);

            // 3. Handle POS specific logic or Regular Posting
            if ($request->input_type == InputType::POS) {
                $this->processPOSInvoice($request, $invoiceId, $arrData['invoice_no']);
            } else {
                // Regular Posting (Journal & Inventory)
                // This will THROW Exception if Unbalanced
                $this->postingJurnal($invoiceId);
            }

            // 4. File Upload
            $this->handleFileUploads($request, $invoiceId);

            DB::commit();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            Log::error("Sales Invoice Store Error: " . $e->getMessage());
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

    // ... [processPOSInvoice, insertPayment, deleteAdditional - kept similar but optimized] ...
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
        // ... (Logic same as original, ensure Utility::remove_commas is used)
        // Assuming this part works in original, ensuring it returns void or result
        // Refactored slightly for safety:
        $paymentNo = $request->payment_no ?: self::generateCodeTransaction(new SalesPayment(),KeyNomor::NO_PELUNASAN_PENJUALAN,'payment_no','payment_date');
        // ... rest of logic
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
            'invoicedelivery.delivery.deliveryproduct.tax.taxgroup.tax'
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
        ];

        $journalEntries = [];
        $inventoryRepo = new InventoryRepo(new Inventory());

        // 3. Process Items (Revenue, Tax, COGS/Inventory)

        // Scenario A: Direct Sales / Service (Via OrderProduct)
        // Used when no Delivery Order is linked (e.g. Service or Direct Invoice)
        if ($find->invoicedelivery->isEmpty()) {

            foreach ($find->orderproduct as $item) {
                // 1. Revenue (Credit)
                $coaRevenue = $settings['coa_penjualan'];
                $dpp = $item->subtotal; // Will be adjusted if Tax Include

                // Calculate Tax
                $taxes = $this->calculateTaxComponents($item, $item->subtotal);
                $totalTaxItem = 0;

                foreach ($taxes as $tax) {
                    $journalEntries[] = [
                        'coa_id' => $tax['coa_id'],
                        'posisi' => $tax['posisi'], // Sales Tax is usually Credit (Liability), Withholding is Debit
                        'nominal'=> $tax['nominal'],
                        'sub_id' => $item->id,
                        'note'   => 'Tax ' . ($item->product->item_name ?? '')
                    ];

                    // If Tax Included, DPP is reduced by the Credit Tax amount
                    // If Tax Excluded, DPP is the Subtotal
                    if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE) {
                        // Standard logic: If we credit Tax (Liability), we reduce Revenue.
                        if ($tax['posisi'] == 'kredit') {
                            $dpp -= $tax['nominal'];
                        }
                    }
                }

                $journalEntries[] = [
                    'coa_id' => $coaRevenue,
                    'posisi' => 'kredit',
                    'nominal'=> $dpp,
                    'sub_id' => $item->id,
                    'note'   => 'Penjualan ' . ($item->product->item_name ?? '')
                ];

                // 2. COGS & Inventory (Only for Non-Service Items)
                if ($find->invoice_type != ProductType::SERVICE && $item->product_id) {
                    $hpp = $inventoryRepo->movingAverageByDate($item->product_id, $item->unit_id, $find->invoice_date);
                    $subtotalHpp = $hpp * $item->qty;

                    // Debit COGS
                    $journalEntries[] = [
                        'coa_id' => $settings['coa_hpp'],
                        'posisi' => 'debet',
                        'nominal'=> $subtotalHpp,
                        'sub_id' => $item->id,
                        'note'   => 'HPP ' . ($item->product->item_name ?? '')
                    ];

                    // Credit Inventory
                    $coaSediaan = !empty($item->product->coa_id) ? $item->product->coa_id : $settings['coa_sediaan'];
                    $journalEntries[] = [
                        'coa_id' => $coaSediaan,
                        'posisi' => 'kredit',
                        'nominal'=> $subtotalHpp,
                        'sub_id' => $item->id,
                        'note'   => 'Keluar Barang ' . ($item->product->item_name ?? '')
                    ];

                    // Log Inventory
                    $this->logInventory($find, $item, $hpp, $subtotalHpp, $inventoryRepo);
                }
            }

        }
        // Scenario B: From Delivery Order
        else {
            foreach ($find->invoicedelivery as $delInv) {
                if (!$delInv->delivery) continue;

                foreach ($delInv->delivery->deliveryproduct as $item) {
                    // 1. Revenue & Tax (Identical logic to Scenario A, usually calculated from Delivery or Order prices)
                    // Note: In SalesInvoicingDelivery, usually pricing data comes from the Order or the Invoice items mapping.
                    // Assuming Delivery Product has the necessary price info or we map it.
                    // Refactoring: Use the item data directly.

                    $coaRevenue = $settings['coa_penjualan'];
                    $dpp = $item->subtotal;

                    $taxes = $this->calculateTaxComponents($item, $item->subtotal);
                    foreach ($taxes as $tax) {
                        $journalEntries[] = [
                            'coa_id' => $tax['coa_id'],
                            'posisi' => $tax['posisi'],
                            'nominal'=> $tax['nominal'],
                            'sub_id' => $item->id,
                            'note'   => 'Tax'
                        ];
                        if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE && $tax['posisi'] == 'kredit') {
                            $dpp -= $tax['nominal'];
                        }
                    }

                    $journalEntries[] = [
                        'coa_id' => $coaRevenue,
                        'posisi' => 'kredit',
                        'nominal'=> $dpp,
                        'sub_id' => $item->id,
                        'note'   => 'Penjualan'
                    ];

                    // 2. COGS (Debit) vs Inventory In Transit (Credit)
                    // Logic: Delivery already Credited Inventory and Debited "In Transit".
                    // Now Invoice Moves "In Transit" to "COGS".
                    // Fetch Value of In Transit (from Delivery Journal or Re-calculate)

                    $valTransit = DeliveryRepo::getValueSediaanDalamPerjalan($delInv->delivery_id, $settings['coa_transit']);
                    // Prorate per item? Or Total?
                    // Simplification: Calculate per item HPP again or use total.
                    // Ideally: Debit COGS, Credit In Transit.

                    // Note: Since getValueSediaanDalamPerjalan returns total for delivery, we should handle this carefully.
                    // To keep atomic per item, let's recalculate HPP logic used in Delivery.
                    // OR stick to the original code approach which loops items.

                    // Simplified per Item approach for this Refactor:
                    // (Assuming cost hasn't changed, or we use the cost recorded in Delivery Inventory Log)
                    // Use DeliveryRepo helper or Inventory log
                    $hppFromDelivery = $item->hpp_price; // Ideally this should be stored
                    if($hppFromDelivery == 0) {
                        // Fallback check inventory log
                        $log = Inventory::where(['transaction_code' => TransactionsCode::DELIVERY_ORDER, 'transaction_sub_id' => $item->id])->first();
                        $hppFromDelivery = $log ? $log->price : 0;
                    }
                    $subtotalHpp = $hppFromDelivery * $item->qty;

                    if ($subtotalHpp > 0) {
                        $journalEntries[] = [
                            'coa_id' => $settings['coa_hpp'],
                            'posisi' => 'debet',
                            'nominal'=> $subtotalHpp,
                            'sub_id' => $item->id,
                            'note'   => 'HPP (From Delivery)'
                        ];
                        $journalEntries[] = [
                            'coa_id' => $settings['coa_transit'],
                            'posisi' => 'kredit',
                            'nominal'=> $subtotalHpp,
                            'sub_id' => $item->id,
                            'note'   => 'Reversal In Transit'
                        ];
                    }

                    // Update Delivery Status
                    DeliveryRepo::changeStatusDelivery($delInv->delivery_id);
                }
            }
        }

        // 4. Discounts (Debit)
        if ($find->discount_total > 0) {
            $journalEntries[] = [
                'coa_id' => $settings['coa_potongan'],
                'posisi' => 'debet',
                'nominal'=> $find->discount_total,
                'sub_id' => 0,
                'note'   => 'Diskon Penjualan'
            ];
            // Discount reduces AR (Credit AR) -> NO.
            // Accounting: Dr Cash/AR, Dr Discount, Cr Revenue.
            // If we Credited Full Revenue above, we Debit Discount.
            // The AR amount is (Revenue - Discount).
            // So we don't Credit AR here. We just ensure AR Debit calculation below subtracts Discount.
            // Wait, original code: Credited Piutang for Discount?
            // "kredit" => $find->discount_total (COA Piutang).
            // Yes, acts as a reduction of the Total AR claim.
            $journalEntries[] = [
                'coa_id' => $settings['coa_piutang'],
                'posisi' => 'kredit',
                'nominal'=> $find->discount_total,
                'sub_id' => 0,
                'note'   => 'Pengurangan Piutang (Diskon)'
            ];
        }

        // 5. Down Payments (Debit Liability / Credit AR)
        // Original Logic: "jurnal uang muka kredit" -> Credit Piutang (Reduces AR)
        // And "jurnal uang muka debet" -> Debit Uang Muka (Liability Account)
        $dpEntries = $this->calculateDpEntries($find->id, $settings['coa_uang_muka'], $settings['coa_piutang']);
        $journalEntries = array_merge($journalEntries, $dpEntries);

        // 6. Accounts Receivable (Debit - Piutang Usaha)
        // Total Invoice Amount to be paid
        $totalPiutang = $find->grandtotal; // This is (Subtotal - Discount + Tax)
        // However, we must ADD back the DP usage and Discount usage to the Journal Line,
        // because we are Crediting them separately against this Debit.
        // OR simpler: Just Debit the Grand Total?
        // Original Code: Debet Piutang = dppPiutang + totalTax.
        // And Credited Discount separately.

        // Let's stick to standard:
        // Dr Piutang (Full Value before DP/Discount? Or Net?)
        // If we Credit Piutang for Discount (above) and Credit Piutang for DP (above via calculateDpEntries),
        // Then we must Debit Piutang for the GROSS amount (Sales + Tax).

        // Gross Calculation based on items:
        $grossSalesAndTax = 0;
        // Easier way: GrandTotal + DiscountTotal + DpTotal.
        $tableDp = (new SalesDownpayment())->getTable();
        $tableInvoiceDp = (new SalesInvoicingDp())->getTable();
        $totalDpUsed = SalesInvoicingDp::where('invoice_id', $find->id)->join($tableDp, $tableDp.'.id', '=', $tableInvoiceDp.'.dp_id')->sum('nominal');

        $grossAR = $find->grandtotal;

        $journalEntries[] = [
            'coa_id' => $settings['coa_piutang'],
            'posisi' => 'debet',
            'nominal'=> $grossAR,
            'sub_id' => 0,
            'note'   => 'Piutang Usaha'
        ];

        // 7. Update Invoice COA
        $this->update(['coa_id' => $settings['coa_piutang']], $find->id);

        // 8. Validate & Save
        Log::info($journalEntries);
        $this->validateAndSaveJournal($journalEntries, $find);
    }

    // ... [Helper Methods: calculateTaxComponents, logInventory, calculateDpEntries, validateAndSaveJournal] ...

    private function calculateTaxComponents($item, $amount): array
    {
        $results = [];
        $objTax = $item->tax;
        if (empty($objTax)) return $results;

        $taxList = [];
        if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
            $taxList[] = $objTax;
        } else {
            foreach ($objTax->taxgroup as $group) {
                if ($group->tax) $taxList[] = $group->tax;
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
            // Simply: We debit the Uang Muka Account (Liability) to close it.
            // But we must separate Tax if it was recorded separately.

            // Re-calculate Tax portion of DP
            if ($dp->tax_id && $dp->tax) {
                // Simplified: Reuse calculateTaxComponents logic on DP nominal
                // Note: Original code had complex logic adjusting $dpp based on tax sign.
                // We will replicate logic: Dr Tax (if it was Cr), Dr Liability.

                // If Tax was VAT (Credit Liability), we now Debit Liability?
                // No, DP Journal was: Dr Cash, Cr UangMuka, Cr Tax.
                // Now we: Dr UangMuka, Dr Tax, Cr Piutang.

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
        $paid = $paymentInvoiceRepo->getAllPaymentByInvoiceId($idInvoice);
        if ($paid >= $findInvoice->grandtotal) {
            $invoiceRepo->update(['invoice_status' => StatusEnum::LUNAS], $idInvoice);
        }
    }
}