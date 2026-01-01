<?php

namespace Icso\Accounting\Repositories\Pembelian\Retur;

use Exception;
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

    // ... [Keep getAllDataBy and getAllTotalDataBy as they were] ...
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
                $queryTopLevel->orWhereHas('receive', function ($queryReceive) use($search) {
                    $queryReceive->where('receive_no', 'like', '%' .$search. '%');
                });
            });
        })->orderBy('retur_date','desc')
            ->with(['vendor','receive','invoice','returproduct.product', 'returproduct.tax.taxgroup.tax','returproduct.unit'])
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
                $queryTopLevel->orWhereHas('receive', function ($queryReceive) use($search) {
                    $queryReceive->where('receive_no', 'like', '%' .$search. '%');
                });
            });
        })->orderBy('retur_date','desc')->count();
    }

    /**
     * Store method with strict Transaction and Balance Check
     */
    public function store(Request $request, array $other = []): bool
    {
        $userId = $request->user_id;
        $id = $request->id;
        $inventoryRepo = new InventoryRepo(new Inventory());

        // Prepare Header Data
        $data = $this->gatherHeaderData($request);
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
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
                $data['retur_status'] = $statusRetur;
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['created_by'] = $userId;
                $data['reason'] = "";
                $res = $this->create($data);
                $returId = $res->id;
            } else {
                $this->update($data, $id);
                $returId = $id;
                $this->deleteAdditional($returId);
            }

            // 2. Process Products & Inventory
            $products = is_array($request->returproduct) ? $request->returproduct : json_decode(json_encode($request->returproduct));

            if (!empty($products)) {
                foreach ($products as $item) {
                    $item = (object)$item;

                    // Save Detail
                    $resItem = PurchaseReturProduct::create([
                        'qty'               => $item->qty,
                        'product_id'        => $item->product_id,
                        'unit_id'           => $item->unit_id,
                        'tax_id'            => $item->tax_id ?? 0,
                        'tax_percentage'    => $item->tax_percentage ?? 0,
                        'hpp_price'         => (float) Utility::remove_commas($item->hpp_price ?? 0),
                        'buy_price'         => (float) Utility::remove_commas($item->buy_price ?? 0),
                        'tax_type'          => $item->tax_type ?? '',
                        'discount_type'     => $item->discount_type ?? '',
                        'discount'          => (float) Utility::remove_commas($item->discount ?? 0),
                        'subtotal'          => (float) Utility::remove_commas($item->subtotal ?? 0),
                        'receive_product_id'=> $item->receive_product_id ?? 0,
                        'order_product_id'  => $item->order_product_id ?? 0,
                        'multi_unit'        => 0,
                        'retur_id'          => $returId,
                    ]);

                    // Save Inventory Log (Outgoing)
                    $reqInv = new Request();
                    $reqInv->coa_id = $item->product->coa_id ?? 0; // Assuming frontend sends object, else fetch product
                    $reqInv->user_id = $userId;
                    $reqInv->inventory_date = $data['retur_date'];
                    $reqInv->transaction_code = TransactionsCode::RETUR_PEMBELIAN;
                    $reqInv->qty_out = $item->qty;
                    $reqInv->warehouse_id = $request->warehouse_id;
                    $reqInv->product_id = $item->product_id;
                    $reqInv->price = (float) Utility::remove_commas($item->hpp_price ?? 0);
                    $reqInv->note = $data['note'];
                    $reqInv->unit_id = $item->unit_id;
                    $reqInv->transaction_id = $returId;
                    $reqInv->transaction_sub_id = $resItem->id;

                    $inventoryRepo->store($reqInv);
                }
            }

            // 3. Link to Payment (Reduction of Debt)
            // Note: This creates a payment record. If Journal fails, DB::rollBack will remove this too.
            InvoiceRepo::insertIntoPaymentFromRetur($request->invoice_id, $returId, $data['retur_date'], $data['total']);

            // 4. Posting Jurnal (CRITICAL)
            // Will THROW Exception if Unbalanced
            $this->postingJurnal($returId);

            // 5. File Upload
            $this->handleFileUploads($request->file('files'), $returId, $userId);

            DB::commit();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            Log::error("Retur Store Error: " . $e->getMessage());
            return false;
        }
    }

    private function gatherHeaderData(Request $request)
    {
        $returNo = $request->retur_no ?: self::generateCodeTransaction(new PurchaseRetur(), KeyNomor::NO_RETUR_PEMBELIAN, 'retur_no', 'retur_date');

        return [
            'retur_no'   => $returNo,
            'retur_date' => $request->retur_date ? Utility::changeDateFormat($request->retur_date) : date('Y-m-d'),
            'note'       => $request->note,
            'subtotal'   => (float) Utility::remove_commas($request->subtotal),
            'total_tax'  => $request->total_tax ? (float) Utility::remove_commas($request->total_tax) : 0,
            'total'      => $request->total ? (float) Utility::remove_commas($request->total) : 0,
            'vendor_id'  => $request->vendor_id ?? 0,
            'receive_id' => $request->receive_id ?? 0,
            'invoice_id' => $request->invoice_id ?? 0,
            'updated_by' => $request->user_id,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Refactored Posting Jurnal with Balance Check
     */
    public function postingJurnal($id)
    {
        // 1. Eager Load
        $find = $this->model->with(['returproduct.product', 'returproduct.tax.taxgroup.tax', 'vendor'])->find($id);

        if (!$find) return;

        // 2. Settings
        $coaUtangUsaha = SettingRepo::getOptionValue(SettingEnum::COA_UTANG_USAHA);
        $coaSediaan    = SettingRepo::getOptionValue(SettingEnum::COA_SEDIAAN);

        $journalEntries = [];
        $note = !empty($find->note) ? $find->note : 'Retur Pembelian ' . ($find->vendor->vendor_name ?? '');

        // 3. Debit: Accounts Payable (Reduce Debt)
        // Total value of return (Subtotal + Tax) is what we don't pay.
        $journalEntries[] = [
            'coa_id' => $coaUtangUsaha,
            'posisi' => 'debet',
            'nominal'=> (float) $find->total,
            'sub_id' => 0,
            'note'   => $note
        ];

        // 4. Credits: Inventory & Taxes
        foreach ($find->returproduct as $item) {
            // A. Credit Inventory (Reduce Asset)
            // Value = Qty * HPP
            $coaInventory = $item->product->coa_id ?? $coaSediaan;
            $subtotalHpp = $item->qty * (float) $item->hpp_price;

            $journalEntries[] = [
                'coa_id' => $coaInventory,
                'posisi' => 'kredit',
                'nominal'=> $subtotalHpp,
                'sub_id' => $item->id,
                'note'   => $note . ' (' . ($item->product->item_name ?? '') . ')'
            ];

            // B. Credit Tax (Reverse Input VAT)
            // Use helper to handle Single/Group tax logic
            $taxes = $this->calculateTaxComponents($item);
            foreach ($taxes as $tax) {
                // Return implies reversing the original transaction.
                // Purchase: Debit PPN Masukan.
                // Retur: Credit PPN Masukan.
                $journalEntries[] = [
                    'coa_id' => $tax['coa_id'],
                    'posisi' => 'kredit',
                    'nominal'=> $tax['nominal'],
                    'sub_id' => $item->id,
                    'note'   => $note . ' (Tax)'
                ];
            }
        }

        // 5. Validate & Save
        $this->validateAndSaveJournal($journalEntries, $find);
    }

    /**
     * Helper to Calculate Tax Components for Return
     */
    private function calculateTaxComponents($item): array
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
            // Calculate Tax Amount based on item subtotal (which is usually price * qty)
            // For Returns, we calculate tax from the returned amount.
            // Note: ReturProduct subtotal usually stores the price-based subtotal.
            // We use item->subtotal (Price * Qty) to calculate the Tax portion to reverse.

            $calcFunc = ($item->tax_type == TypeEnum::TAX_TYPE_INCLUDE) ? 'hitungIncludeTax' : 'hitungTaxDpp';
            // Note: helpers might differ, using generic calculation logic:
            $getTax = Helpers::hitungTaxDpp($item->subtotal, $taxCfg->id, $item->tax_type, $taxCfg->tax_percentage);

            if (!empty($getTax) && isset($getTax[TypeEnum::PPN])) {
                $results[] = [
                    'coa_id'  => $taxCfg->purchase_coa_id,
                    'nominal' => $getTax[TypeEnum::PPN]
                ];
            }
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

        // Tolerance of 1 Rupiah
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
                'transaction_code'      => TransactionsCode::RETUR_PEMBELIAN,
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
                    PurchaseReturMeta::create([
                        'retur_id' => $returId,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload
                    ]);
                }
            }
        }
    }

    public function deleteAdditional($idRetur)
    {
        PurchaseReturProduct::where('retur_id', $idRetur)->delete();
        PurchaseReturMeta::where('retur_id', $idRetur)->delete();
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::RETUR_PEMBELIAN, $idRetur);
        Inventory::where('transaction_code', TransactionsCode::RETUR_PEMBELIAN)
            ->where('transaction_id', $idRetur)->delete();
        PurchasePaymentInvoice::where('retur_id', $idRetur)->delete();
    }
}