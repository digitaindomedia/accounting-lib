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
            Log::error("Sales Invoice Store Error: " . $e->getMessage() . ' ini payload nya ' . json_encode($request->all()));
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
        $orderId = $request->order_id ?? $request->input('order.id');

        // If invoice is created from Sales Order (Service type), don't create new products
        // Just update the invoice_id on existing order products
        if (!empty($orderId) && $request->invoice_type == ProductType::SERVICE) {
            Log::info("Invoice from Sales Order (Service). Updating existing orderproduct with invoice_id={$idInvoice}");

            // Update existing order products to link with this invoice
            SalesOrderProduct::where('order_id', $orderId)
                ->where('invoice_id', 0) // Only update products not yet invoiced
                ->update(['invoice_id' => $idInvoice]);

            return;
        }

        // For direct invoice (no order_id) or Item type, create new products
        $products = $request->orderproduct;
        if (empty($products)) {
            $products = $request->input('order.orderproduct');
        }

        if (empty($products)) {
            Log::info("No orderproduct data to process");
            return;
        }

        $products = is_array($products) ? json_decode(json_encode($products)) : $products;

        foreach ($products as $item) {
            $item = (object)$item;

            $taxType = $item->tax_type ?? '';
            $taxId = $item->tax_id ?? 0;

            // If item has original ID (from order), check if it already exists
            if (isset($item->id)) {
                $existingProduct = SalesOrderProduct::find($item->id);
                if ($existingProduct) {
                    // Update existing product with invoice_id
                    $existingProduct->update(['invoice_id' => $idInvoice]);
                    Log::info("Updated existing orderproduct {$item->id} with invoice_id={$idInvoice}");
                    continue;
                }

                // If not found, preserve original tax configuration
                $originalProduct = SalesOrderProduct::where('order_id', $orderId)
                    ->where('product_id', $item->product_id ?? 0)
                    ->first();
                if ($originalProduct) {
                    $taxType = $originalProduct->tax_type;
                    $taxId = $originalProduct->tax_id;
                }
            }

            // Fallback to request/order level tax_type if still empty
            if (empty($taxType)) {
                $taxType = $request->input('order.tax_type', $request->tax_type ?? '');
            }

            // Create new orderproduct
            SalesOrderProduct::create([
                'qty'               => $item->qty,
                'qty_left'          => $item->qty,
                'product_id'        => $item->product_id ?? 0,
                'unit_id'           => $item->unit_id ?? 0,
                'tax_id'            => $taxId,
                'tax_percentage'    => $item->tax_percentage ?? 0,
                'price'             => Utility::remove_commas($item->price ?? 0),
                'tax_type'          => $taxType,
                'discount_type'     => $item->discount_type ?? '',
                'discount'          => Utility::remove_commas($item->discount ?? 0),
                'subtotal'          => Utility::remove_commas($item->subtotal ?? 0),
                'multi_unit'        => 0,
                'order_id'          => $orderId ?? 0,
                'invoice_id'        => $idInvoice
            ]);

            Log::info("Created new orderproduct for invoice {$idInvoice}");
        }
    }

    private function processDp(Request $request, $idInvoice)
    {
        $dps = $request->dp;
        if (empty($dps)) {
            $dps = $request->input('order.dp');
        }
        
        if (!empty($dps)) {
            $dps = is_array($dps) ? json_decode(json_encode($dps)) : $dps;
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
        $deliveries = $request->delivery;
        if (empty($deliveries)) {
            $deliveries = $request->input('order.delivery');
        }

        if (!empty($deliveries)) {
            $deliveries = is_array($deliveries) ? json_decode(json_encode($deliveries)) : $deliveries;
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
        $this->insertPayment($request, $idInvoice, $noInvoice);
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
            'invoicedelivery.delivery.deliveryproduct.tax.taxgroup.tax'
        ])->find($idInvoice);

        if (!$find) return;
        
        Log::info("Posting Jurnal Invoice $idInvoice. Products: " . $find->orderproduct->count());

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
                // Determine Revenue COA: Use product's coa_id if set, otherwise use default
                $coaRevenue = (!empty($item->product->coa_id) && $item->product->coa_id != 0)
                    ? $item->product->coa_id
                    : $settings['coa_penjualan'];

                $subtotal = $item->subtotal;

                // Important: Use tax_type from orderproduct, NOT from invoice level
                $itemTaxType = $item->tax_type;

                Log::info("Processing item {$item->id}: subtotal={$subtotal}, tax_type={$itemTaxType}, tax_id={$item->tax_id}, coa_revenue={$coaRevenue}");

                // Calculate Tax Components
                $taxes = $this->calculateTaxComponents($item, $subtotal);
                $totalTaxAmount = 0;

                foreach ($taxes as $tax) {
                    $journalEntries[] = [
                        'coa_id' => $tax['coa_id'],
                        'posisi' => $tax['posisi'],
                        'nominal'=> $tax['nominal'],
                        'sub_id' => $item->id,
                        'note'   => 'Tax ' . ($item->product->item_name ?? '')
                    ];

                    // Sum up tax for DPP calculation
                    if ($tax['posisi'] == 'kredit') {
                        $totalTaxAmount += $tax['nominal'];
                    }
                }

                // Calculate DPP (Revenue before tax)
                // INCLUDE: DPP = Subtotal - Tax (tax already included in subtotal)
                // EXCLUDE: DPP = Subtotal (tax is additional, not included)
                $dpp = $subtotal;
                if ($itemTaxType == TypeEnum::TAX_TYPE_INCLUDE) {
                    $dpp = $subtotal - $totalTaxAmount;
                }
                // For EXCLUDE: DPP remains as subtotal

                Log::info("DPP calculation: subtotal={$subtotal}, tax_type={$itemTaxType}, totalTax={$totalTaxAmount}, dpp={$dpp}");

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

                    // Credit Inventory (use product's inventory COA if set)
                    $coaSediaan = (!empty($item->product->coa_id) && $item->product->coa_id != 0)
                        ? $item->product->coa_id
                        : $settings['coa_sediaan'];

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
                    // Determine Revenue COA: Use product's coa_id if set, otherwise use default
                    $coaRevenue = (!empty($item->product->coa_id) && $item->product->coa_id != 0)
                        ? $item->product->coa_id
                        : $settings['coa_penjualan'];

                    $dpp = $item->subtotal;

                    $taxes = $this->calculateTaxComponents($item, $item->subtotal);
                    $totalTaxAmount = 0;

                    foreach ($taxes as $tax) {
                        $journalEntries[] = [
                            'coa_id' => $tax['coa_id'],
                            'posisi' => $tax['posisi'],
                            'nominal'=> $tax['nominal'],
                            'sub_id' => $item->id,
                            'note'   => 'Tax ' . ($item->product->item_name ?? '')
                        ];
                        if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE && $tax['posisi'] == 'kredit') {
                            $totalTaxAmount += $tax['nominal'];
                        }
                    }

                    // Calculate DPP for delivery product
                    if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE) {
                        $dpp = $item->subtotal - $totalTaxAmount;
                    }

                    $journalEntries[] = [
                        'coa_id' => $coaRevenue,
                        'posisi' => 'kredit',
                        'nominal'=> $dpp,
                        'sub_id' => $item->id,
                        'note'   => 'Penjualan ' . ($item->product->item_name ?? '')
                    ];

                    // 2. COGS (Debit) vs Inventory In Transit (Credit)
                    $valTransit = DeliveryRepo::getValueSediaanDalamPerjalan($delInv->delivery_id, $settings['coa_transit']);

                    $hppFromDelivery = $item->hpp_price;
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
            $journalEntries[] = [
                'coa_id' => $settings['coa_piutang'],
                'posisi' => 'kredit',
                'nominal'=> $find->discount_total,
                'sub_id' => 0,
                'note'   => 'Pengurangan Piutang (Diskon)'
            ];
        }

        // 5. Down Payments (Debit Liability / Credit AR)
        $dpEntries = $this->calculateDpEntries($find->id, $settings['coa_uang_muka'], $settings['coa_piutang']);
        $journalEntries = array_merge($journalEntries, $dpEntries);

        // 6. Accounts Receivable (Debit - Piutang Usaha)
        
        // Calculate Total DP Used
        $tableDp = (new SalesDownpayment())->getTable();
        $tableInvoiceDp = (new SalesInvoicingDp())->getTable();
        $totalDpUsed = SalesInvoicingDp::where('invoice_id', $find->id)
            ->join($tableDp, $tableDp.'.id', '=', $tableInvoiceDp.'.dp_id')
            ->sum($tableDp.'.nominal');

        // Gross AR Calculation:
        // For EXCLUDE tax: Total Invoice = Subtotal + Tax
        // Gross AR = Subtotal + Tax + DP + Discount (because we credit AR for DP and Discount later)
        // For this case: AR = 5,000,000 + 600,000 = 5,600,000
        // We don't add DP here because grandtotal from frontend already excludes DP

        // Check if invoice uses EXCLUDE tax type from orderproduct
        $totalCredits = 0;
        $totalDebits = 0;

        foreach ($journalEntries as $entry) {
            if ($entry['posisi'] == 'kredit') {
                $totalCredits += $entry['nominal'];
            } else {
                $totalDebits += $entry['nominal'];
            }
        }

        // AR (Debit) should balance: Total Credits - Total Debits (so far)
        $grossAR = $totalCredits - $totalDebits;

        Log::info("AR Calculation from entries: totalCredits={$totalCredits}, totalDebits={$totalDebits}, calculatedAR={$grossAR}");

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

    private function calculateTaxComponents($item, $amount): array
    {
        $results = [];
        $objTax = $item->tax;

        // Debug logging
        Log::info("Tax calculation for item {$item->id}: tax_id={$item->tax_id}, tax_percentage={$item->tax_percentage}, tax_type={$item->tax_type}, amount={$amount}");

        if (empty($objTax)) {
            Log::warning("No tax object found for item {$item->id} with tax_id={$item->tax_id}");
            return $results;
        }

        $taxList = [];
        if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
            $taxList[] = $objTax;
        } else {
            foreach ($objTax->taxgroup as $group) {
                if ($group->tax) $taxList[] = $group->tax;
            }
        }

        foreach ($taxList as $taxCfg) {
            $percentage = ($item->tax_percentage > 0) ? $item->tax_percentage : $taxCfg->tax_percentage;

            // Determine calculation function based on tax_type and is_dpp_nilai_Lain
            $func = 'hitungExcludeTax'; // default

            if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE) {
                $func = ($taxCfg->is_dpp_nilai_Lain == 1)
                    ? 'hitungIncludeTaxDppNilaiLain'
                    : 'hitungIncludeTax';
            } else {
                $func = ($taxCfg->is_dpp_nilai_Lain == 1)
                    ? 'hitungExcludeTaxDppNilaiLain'
                    : 'hitungExcludeTax';
            }

            Log::info("Using tax function: {$func}, is_dpp_nilai_Lain={$taxCfg->is_dpp_nilai_Lain}");

            $calc = Helpers::$func($percentage, $amount);
            $taxNominal = $calc[TypeEnum::PPN] ?? 0;

            Log::info("Tax calculated: {$taxCfg->tax_name}, percentage={$percentage}, nominal={$taxNominal}, sign={$taxCfg->tax_sign}");

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

        Log::info("Total tax components: " . count($results));
        return $results;
    }

    private function calculateDpEntries($invoiceId, $coaUangMuka, $coaPiutang)
    {
        $entries = [];
        $dps = SalesInvoicingDp::where('invoice_id', $invoiceId)->with(['downpayment.tax.taxgroup.tax'])->get();

        foreach ($dps as $row) {
            $dp = $row->downpayment;
            $nominal = $dp->nominal;
            $dppDp = $nominal;
            $totalTaxDp = 0;

            // Handle DP Tax Reversal
            if ($dp->tax_id && $dp->tax) {
                $taxes = $this->calculateTaxComponents($dp, $nominal);

                foreach($taxes as $tax) {
                    // Reverse the position: if originally Credit, now Debit
                    $reversePos = ($tax['posisi'] == 'kredit') ? 'debet' : 'kredit';
                    $entries[] = [
                        'coa_id' => $tax['coa_id'],
                        'posisi' => $reversePos,
                        'nominal'=> $tax['nominal'],
                        'sub_id' => $dp->id,
                        'note'   => 'Reversal Tax DP'
                    ];

                    // Sum tax amount for DPP calculation
                    if ($tax['posisi'] == 'kredit') {
                        $totalTaxDp += $tax['nominal'];
                    }
                }

                // Calculate DPP DP:
                // If tax exists and was recorded, DPP = Nominal - Tax
                // This is because original DP journal was:
                // Credit: Uang Muka (DPP) + Credit: Tax
                // So we need to reverse only the DPP portion
                $dppDp = $nominal - $totalTaxDp;

                Log::info("DP with tax: nominal={$nominal}, tax={$totalTaxDp}, dpp={$dppDp}");
            } else {
                // No tax on DP
                $dppDp = $nominal;
                Log::info("DP without tax: nominal={$nominal}, dpp={$dppDp}");
            }

            // Debit Uang Muka (Close Liability - DPP portion only)
            $entries[] = [
                'coa_id' => $coaUangMuka,
                'posisi' => 'debet',
                'nominal'=> $dppDp,
                'sub_id' => $dp->id,
                'note'   => 'Penggunaan Uang Muka'
            ];

            // Credit Piutang (Reduce Invoice Claim by FULL nominal including tax)
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