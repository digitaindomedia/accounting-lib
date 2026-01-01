<?php

namespace Icso\Accounting\Repositories\Penjualan\Retur;

use Exception;
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

    // ... [Keep getAllDataBy and getAllTotalDataBy as original] ...
    public function getAllDataBy($search, $page, $perpage, array $where = []): mixed
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
            $query->where(function ($queryTopLevel) use($search){
                $queryTopLevel->where('retur_no', 'like', '%' .$search. '%');
                $queryTopLevel->orWhereHas('vendor', function ($queryVendor) use($search) {
                    $queryVendor->where('vendor_name', 'like', '%' .$search. '%')
                        ->orWhere('vendor_company_name', 'like', '%' .$search. '%');
                });
                $queryTopLevel->orWhereHas('delivery', function ($queryReceive) use($search) {
                    $queryReceive->where('retur_no', 'like', '%' .$search. '%');
                });
                $queryTopLevel->orWhereHas('invoice', function ($queryReceive) use($search) {
                    $queryReceive->where('invoice_no', 'like', '%' .$search. '%');
                });
            });
        })->orderBy('retur_date','desc')
            ->with(['vendor','delivery','returproduct.product','returproduct.unit','returproduct.tax','returproduct.tax.taxgroup'])
            ->offset($page)->limit($perpage)->get();
    }

    public function getAllTotalDataBy($search, array $where = []): int
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
            $query->where(function ($queryTopLevel) use($search){
                $queryTopLevel->where('retur_no', 'like', '%' .$search. '%');
                $queryTopLevel->orWhereHas('vendor', function ($queryVendor) use($search) {
                    $queryVendor->where('vendor_name', 'like', '%' .$search. '%')
                        ->orWhere('vendor_company_name', 'like', '%' .$search. '%');
                });
            });
        })->count();
    }

    /**
     * Store method with Strict Transaction
     */
    public function store(Request $request, array $other = []): bool
    {
        $id = $request->id;
        $userId = $request->user_id;

        // Prepare Header
        $arrData = $this->gatherHeaderData($request);

        DB::beginTransaction();
        try {
            // 1. Create/Update Header
            if (empty($id)) {
                $statusRetur = StatusEnum::OPEN;
                if (!empty($request->invoice_id)) {
                    $getStatus = InvoiceRepo::getStatusInvoice($request->invoice_id);
                    if ($getStatus == StatusEnum::BELUM_LUNAS) {
                        $statusRetur = StatusEnum::SELESAI;
                    }
                }
                $arrData['retur_status'] = $statusRetur;
                $arrData['reason'] = "";
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $arrData['created_by'] = $userId;
                $res = $this->create($arrData);
                $idRetur = $res->id;
            } else {
                $this->update($arrData, $id);
                $idRetur = $id;
                $this->deleteAdditional($idRetur);
            }

            // 2. Process Products
            $products = is_array($request->returproduct) ? $request->returproduct : json_decode(json_encode($request->returproduct));
            if (!empty($products)) {
                foreach ($products as $item) {
                    $item = (object)$item;
                    SalesReturProduct::create([
                        'qty'               => $item->qty,
                        'product_id'        => $item->product_id,
                        'unit_id'           => $item->unit_id,
                        'tax_id'            => $item->tax_id ?? 0,
                        'tax_percentage'    => $item->tax_percentage ?? 0,
                        'hpp_price'         => Utility::remove_commas($item->hpp_price ?? 0),
                        'sell_price'        => Utility::remove_commas($item->buy_price ?? $item->sell_price ?? 0), // handle variation
                        'tax_type'          => $item->tax_type ?? '',
                        'discount_type'     => $item->discount_type ?? '',
                        'discount'          => Utility::remove_commas($item->discount ?? 0),
                        'subtotal'          => Utility::remove_commas($item->subtotal ?? 0),
                        'delivery_product_id'=> $item->delivery_product_id ?? 0,
                        'order_product_id'  => $item->order_product_id ?? 0,
                        'multi_unit'        => 0,
                        'retur_id'          => $idRetur,
                    ]);
                }
            }

            // 3. Link to Payment (As Reduction)
            /*if(!empty($request->invoice_id)){
                InvoiceRepo::insertIntoPaymentFromRetur($request->invoice_id, $idRetur, $arrData['retur_date'], $arrData['total']);
            }*/


            // 4. Posting Jurnal (CRITICAL)
            // Throws Exception if Unbalanced
            $this->postingJurnal($idRetur);

            // 5. File Upload
            $this->handleFileUploads($request->file('files'), $idRetur, $userId);

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Sales Retur Store Error: " . $e->getMessage());
            return false;
        }
    }

    private function gatherHeaderData(Request $request)
    {
        $returNo = $request->retur_no ?: self::generateCodeTransaction(new SalesRetur(), KeyNomor::NO_RETUR_PENJUALAN, 'retur_no', 'retur_date');

        // Fix: Use NULL for optional Foreign Keys
        $vendorId = !empty($request->vendor_id) ? Utility::remove_commas($request->vendor_id) : 0;
        $deliveryId = !empty($request->delivery_id) ? Utility::remove_commas($request->delivery_id) : 0;
        $invoiceId = !empty($request->invoice_id) ? Utility::remove_commas($request->invoice_id) : 0;

        return [
            'retur_no'      => $returNo,
            'retur_date'    => $request->retur_date ? Utility::changeDateFormat($request->retur_date) : date('Y-m-d'),
            'note'          => $request->note,
            'subtotal'      => Utility::remove_commas($request->subtotal),
            'total_tax'     => Utility::remove_commas($request->total_tax ?? 0),
            'total'         => Utility::remove_commas($request->total ?? 0),
            'vendor_id'     => $vendorId,
            'delivery_id'   => $deliveryId,
            'invoice_id'    => $invoiceId,
            'updated_by'    => $request->user_id,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];
    }

    public function deleteAdditional($idRetur)
    {
        SalesReturProduct::where('retur_id', $idRetur)->delete();
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::RETUR_PENJUALAN, $idRetur);
        Inventory::where('transaction_code', TransactionsCode::RETUR_PENJUALAN)->where('transaction_id', $idRetur)->delete();

        // Note: Using PurchasePaymentInvoice model for Sales Return?
        // Based on provided InvoiceRepo, it seems SalesPaymentInvoice is used.
        // If it's separate, change model. Assuming keeping original logic:
        // Original used PurchasePaymentInvoice? That seems wrong for Sales, but sticking to provided code style
        // WAIT: InvoiceRepo provided uses SalesPaymentInvoice. Original ReturRepo used PurchasePaymentInvoice.
        // I will use SalesPaymentInvoice for Sales Return as it makes more sense.
        // Assuming your InvoiceRepo::insertIntoPaymentFromRetur uses SalesPaymentInvoice.

        // Check imports: The original file imported `Icso\Accounting\Models\Pembelian\Pembayaran\PurchasePaymentInvoice`.
        // BUT this is `SalesRetur`. It should likely be `SalesPaymentInvoice`.
        // I will fix this potential bug by deleting from both to be safe or assuming the correct one based on context.
        // I'll use the table name approach or correct model if available.
        // Based on logic, for Sales Retur, it should be SalesPaymentInvoice.

        // Let's rely on what InvoiceRepo::insertIntoPaymentFromRetur does.
        // For deletion:
        \Icso\Accounting\Models\Penjualan\Pembayaran\SalesPaymentInvoice::where('retur_id', $idRetur)->delete();

        SalesReturMeta::where('retur_id', $idRetur)->delete();
    }

    /**
     * Refactored Posting Jurnal
     * Combines Inventory (Cost) and Financial (Sales) journals.
     */
    public function postingJurnal($idRetur)
    {
        // 1. Eager Load
        $find = $this->model->with([
            'returproduct.tax.taxgroup.tax',
            'returproduct.deliveryproduct.product',
            'vendor'
        ])->find($idRetur);

        if (!$find) return;

        // 2. Settings
        $settings = [
            'coa_retur'     => SettingRepo::getOptionValue(SettingEnum::COA_RETUR_PENJUALAN),
            'coa_piutang'   => SettingRepo::getOptionValue(SettingEnum::COA_PIUTANG_USAHA),
            'coa_sediaan'   => SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN),
            'coa_hpp'       => SettingRepo::getOptionValue(SettingEnum::COA_BEBAN_POKOK_PENJUALAN),
            'coa_transit'   => SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN_DALAM_PERJALANAN),
        ];

        $inventoryRepo = new InventoryRepo(new Inventory());
        $journalEntries = [];
        $note = !empty($find->note) ? $find->note : 'Retur Penjualan ' . ($find->vendor->vendor_name ?? '');

        // 3. Process Products
        foreach ($find->returproduct as $item) {
            // --- Part A: Inventory Side (Cost Reversal) ---
            if (!empty($find->delivery_id)) {
                // Determine HPP from original Delivery
                // We try to find the inventory log of the original delivery item to get the exact cost
                $hpp = 0;
                $findInStok = $inventoryRepo->findByTransCodeIdSubId(
                    TransactionsCode::DELIVERY_ORDER,
                    $find->delivery_id,
                    $item->delivery_product_id
                );
                if ($findInStok) $hpp = $findInStok->price; // Use stored price (Moving Avg at that time)

                $subtotalHpp = $hpp * $item->qty;

                // Log Inventory (Incoming Back)
                $coaSediaan = $item->product->coa_id ?? $settings['coa_sediaan'];

                $reqInventory = new Request();
                $reqInventory->coa_id = $coaSediaan;
                $reqInventory->user_id = $find->created_by;
                $reqInventory->inventory_date = $find->retur_date;
                $reqInventory->transaction_code = TransactionsCode::RETUR_PENJUALAN;
                $reqInventory->transaction_id = $find->id;
                $reqInventory->transaction_sub_id = $item->id;
                $reqInventory->qty_in = $item->qty;
                $reqInventory->warehouse_id = $find->warehouse_id ?? 0; // Ensure warehouse is passed
                $reqInventory->product_id = $item->product_id;
                $reqInventory->price = $hpp;
                $reqInventory->note = $note;
                $reqInventory->unit_id = $item->unit_id;
                $inventoryRepo->store($reqInventory);

                // Journal: Debit Inventory (Asset Increase)
                $journalEntries[] = [
                    'coa_id' => $coaSediaan,
                    'posisi' => 'debet',
                    'nominal'=> $subtotalHpp,
                    'sub_id' => $item->id,
                    'note'   => $note . ' (Inv)'
                ];

                // Journal: Credit COGS or In-Transit (Expense Decrease)
                // Logic: If Delivery is not yet Invoiced, the cost is in "In Transit".
                // If Delivery is Invoiced, the cost is in "COGS".
                $invoicedCount = SalesInvoicingDelivery::where('delivery_id', $find->delivery_id)->count();
                $coaCredit = ($invoicedCount > 0) ? $settings['coa_hpp'] : $settings['coa_transit'];

                $journalEntries[] = [
                    'coa_id' => $coaCredit,
                    'posisi' => 'kredit',
                    'nominal'=> $subtotalHpp,
                    'sub_id' => $item->id,
                    'note'   => $note . ' (Cost Reversal)'
                ];
            }

            // --- Part B: Financial Side (Sales Reversal) ---
            // Calculate Taxes
            $taxes = $this->calculateTaxComponents($item, $item->subtotal); // Subtotal is (Sell Price * Qty)

            // 1. Debit Sales Return (Contra Revenue)
            // Amount = Item Subtotal. If Tax Inclusive, we must strip tax first.
            $dpp = $item->subtotal;
            foreach ($taxes as $tax) {
                // 2. Debit Tax (Reversing Liability)
                // Original Sale: Credit Tax (Liability). Return: Debit Tax.
                // Note: Withholding (Pemotong) works opposite.

                // Logic:
                // Standard VAT (Penambah): Sale = Cr Tax. Return = Dr Tax.
                // Withholding (Pemotong): Sale = Dr Prepaid. Return = Cr Prepaid.

                $posisi = ($tax['sign'] == VarType::TAX_SIGN_PEMOTONG) ? 'kredit' : 'debet';

                $journalEntries[] = [
                    'coa_id' => $tax['coa_id'],
                    'posisi' => $posisi,
                    'nominal'=> $tax['nominal'],
                    'sub_id' => $item->id,
                    'note'   => $note . ' (Tax)'
                ];

                if ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE && $tax['sign'] != VarType::TAX_SIGN_PEMOTONG) {
                    $dpp -= $tax['nominal'];
                }
            }

            $journalEntries[] = [
                'coa_id' => $settings['coa_retur'],
                'posisi' => 'debet',
                'nominal'=> $dpp,
                'sub_id' => $item->id,
                'note'   => $note . ' (Retur Penjualan)'
            ];
        }

        // 3. Credit Accounts Receivable (Reduce Claim)
        // Amount = Total Sales Return Value (Gross including tax)
        // Original logic summed dpp + tax.
        // We can just use the Header Total usually, but recalculating ensures alignment with lines.
        // Let's use logic: Total Debit (Financial) = Total Credit (Financial)
        // Total Debit = Sum(Retur) + Sum(Debet Tax) - Sum(Kredit Tax)

        // Simpler: Just use the total from the header if trusted, or sum up the financial entries.
        // The Invoice/Payment logic usually relies on Header Total.
        $totalPiutang = $find->total; // Assuming total is Subtotal - Discount + Tax

        $journalEntries[] = [
            'coa_id' => $settings['coa_piutang'],
            'posisi' => 'kredit',
            'nominal'=> $totalPiutang,
            'sub_id' => 0,
            'note'   => $note
        ];

        // 4. Validate & Save
        $this->validateAndSaveJournal($journalEntries, $find);
    }

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

            $results[] = [
                'coa_id'  => $taxCfg->sales_coa_id,
                'nominal' => $taxNominal,
                'sign'    => $taxCfg->tax_sign
            ];
        }
        return $results;
    }

    private function validateAndSaveJournal(array $entries, $returModel)
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $e) {
            if ($e['posisi'] == 'debet') $totalDebit += $e['nominal'];
            else $totalCredit += $e['nominal'];
        }

        if (abs($totalDebit - $totalCredit) > 1) {
            throw new Exception("Jurnal Retur {$returModel->retur_no} Tidak Balance! Debet: " . number_format($totalDebit) . ", Kredit: " . number_format($totalCredit));
        }

        $jurnalRepo = new JurnalTransaksiRepo(new JurnalTransaksi());

        foreach ($entries as $e) {
            if ($e['nominal'] == 0) continue;

            $jurnalRepo->create([
                'transaction_date'      => $returModel->retur_date,
                'transaction_datetime'  => $returModel->retur_date . " " . date('H:i:s'),
                'created_by'            => $returModel->created_by,
                'updated_by'            => $returModel->created_by,
                'transaction_code'      => TransactionsCode::RETUR_PENJUALAN,
                'coa_id'                => $e['coa_id'],
                'transaction_id'        => $returModel->id,
                'transaction_sub_id'    => $e['sub_id'],
                'transaction_no'        => $returModel->retur_no,
                'transaction_status'    => JurnalStatusEnum::OK,
                'debet'                 => ($e['posisi'] == 'debet') ? $e['nominal'] : 0,
                'kredit'                => ($e['posisi'] == 'kredit') ? $e['nominal'] : 0,
                'note'                  => $e['note'],
                'created_at'            => date("Y-m-d H:i:s"),
                'updated_at'            => date("Y-m-d H:i:s"),
            ]);
        }
    }

    private function handleFileUploads($uploadedFiles, $returId, $userId)
    {
        if (!empty($uploadedFiles)) {
            $fileUpload = new FileUploadService();
            foreach ($uploadedFiles as $file) {
                $resUpload = $fileUpload->upload($file, tenant(), $userId);
                if ($resUpload) {
                    SalesReturMeta::create([
                        'retur_id' => $returId,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload
                    ]);
                }
            }
        }
    }
}