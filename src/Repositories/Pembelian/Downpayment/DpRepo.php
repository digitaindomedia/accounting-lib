<?php

namespace Icso\Accounting\Repositories\Pembelian\Downpayment;

use Exception;
use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Pembelian\UangMuka\PurchaseDownPayment;
use Icso\Accounting\Models\Pembelian\UangMuka\PurchaseDownPaymentMeta;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
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

class DpRepo extends ElequentRepository
{
    protected $model;

    public function __construct(PurchaseDownPayment $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    // ... [Keep getAllDataBy, getAllTotalDataBy, getAllDataBetweenBy, getAllTotalDataBetweenBy as they are] ...

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        $model = new $this->model;
        return $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%')
                ->orWhere('note', 'like', '%' .$search. '%')
                ->orWhere('no_faktur', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->with(['order','coa'])->orderBy('downpayment_date','desc')->offset($page)->limit($perpage)->get();
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        $model = new $this->model;
        return $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%')
                ->orWhere('note', 'like', '%' .$search. '%')
                ->orWhere('no_faktur', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->count();
    }

    public function getAllDataBetweenBy($search, $page, $perpage, array $where = [], array $whereBetween=[])
    {
        $model = new $this->model;
        return $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%')
                ->orWhere('note', 'like', '%' .$search. '%')
                ->orWhere('no_faktur', 'like', '%' .$search. '%')
                ->orWhereHas('order', function ($query) use ($search) {
                    $query->where('order_no', 'like', '%' .$search. '%');
                });
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
            $query->orWhereHas('order', function ($query) use ($where) {
                $query->where($where);
            });
        })->when(!empty($whereBetween), function ($query) use($whereBetween){
            $query->whereBetween('downpayment_date', $whereBetween);
        })->orderBy('downpayment_date','desc')->with(['order','order.vendor','coa'])->offset($page)->limit($perpage)->get();
    }

    public function getAllTotalDataBetweenBy($search, array $where = [], array $whereBetween=[])
    {
        $model = new $this->model;
        return $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%')
                ->orWhere('note', 'like', '%' .$search. '%')
                ->orWhere('no_faktur', 'like', '%' .$search. '%')
                ->orWhereHas('order', function ($query) use ($search) {
                    $query->where('order_no', 'like', '%' .$search. '%');
                });
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
            $query->orWhereHas('order', function ($query) use ($where) {
                $query->where($where);
            });
        })->when(!empty($whereBetween), function ($query) use($whereBetween){
            $query->whereBetween('downpayment_date', $whereBetween);
        })->orderBy('downpayment_date','desc')->with(['order','order.vendor','coa'])->count();
    }

    /**
     * Store with Strict Transaction and Validation
     */
    public function store(Request $request, array $other = [])
    {
        $id = $request->id;
        $userId = $request->user_id;

        // Prepare Data
        $data = $this->gatherInputData($request);

        DB::beginTransaction();
        try {
            // 1. Save or Update Down Payment Record
            if (empty($id)) {
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['created_by'] = $userId;
                $data['dp_type'] = $request->dp_type;
                $data['downpayment_status'] = StatusEnum::OPEN;
                $res = $this->create($data);
                $resId = $res->id;
            } else {
                $this->update($data, $id);
                $resId = $id;
                // Cleanup old data before reposting
                $this->deleteAdditional($resId);
            }

            // 2. Posting Jurnal (The Critical Step)
            // This method will THROW an exception if journals are not balanced
            $this->postingJurnal($resId);

            // 3. Handle File Uploads
            $this->handleFileUploads($request->file('files'), $resId, $userId);

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("DP Store Failed: " . $e->getMessage());
            // It is often better to rethrow or return false depending on controller expectation.
            // Returning false implies a generic failure.
            return false;
        }
    }

    /**
     * Helper to gather input (Clean Code)
     */
    private function gatherInputData(Request $request)
    {
        $refNo = $request->ref_no ?: self::generateCodeTransaction(new PurchaseDownPayment(), KeyNomor::NO_UANG_MUKA_PEMBELIAN, 'ref_no', 'downpayment_date');

        return [
            'ref_no' => $refNo,
            'downpayment_date' => $request->downpayment_date ? Utility::changeDateFormat($request->downpayment_date) : date("Y-m-d"),
            'faktur_date' => $request->faktur_date ? Utility::changeDateFormat($request->faktur_date) : date('Y-m-d'),
            'nominal' => Utility::remove_commas($request->nominal),
            'faktur_accepted' => $request->faktur_accepted ?? 'no',
            'no_faktur' => $request->no_faktur,
            'note' => $request->note,
            'order_id' => $request->order_id,
            'coa_id' => $request->coa_id ?? 0,
            'tax_id' => $request->tax_id ?? 0,
            'updated_by' => $request->user_id,
            'updated_at' => date('Y-m-d H:i:s'),
            'reason' => "",
            'document' => ""
        ];
    }

    /**
     * Helper to handle files
     */
    private function handleFileUploads($uploadedFiles, $dpId, $userId)
    {
        if (!empty($uploadedFiles) && count($uploadedFiles) > 0) {
            $fileUpload = new FileUploadService();
            foreach ($uploadedFiles as $file) {
                $resUpload = $fileUpload->upload($file, tenant(), $userId);
                if ($resUpload) {
                    PurchaseDownPaymentMeta::create([
                        'dp_id' => $dpId,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload
                    ]);
                }
            }
        }
    }

    /**
     * REFACTORED POSTING JURNAL
     * Ensures Debit == Credit
     */
    public function postingJurnal($idUangMuka)
    {
        // 1. Fetch Data with Eager Loading
        $res = $this->model->with(['order.vendor', 'tax.taxgroup.tax'])->find($idUangMuka);

        if (empty($res)) return;

        // 2. Prepare Settings
        $coaUangMukaPembelian = SettingRepo::getOptionValue(SettingEnum::COA_UANG_MUKA_PEMBELIAN);
        // Default Credit Account (Kas/Bank) is taken from coa_id selected in form, or default setting
        $coaKasBank = !empty($res->coa_id) ? $res->coa_id : SettingRepo::getOptionValue(SettingEnum::COA_KAS_BANK);

        $nominal = $res->nominal; // Total Money Out
        $dpp = $nominal; // Amount to be recorded as Asset (Uang Muka) - starts as full amount

        // Prepare Note
        $supplierName = $res->order->vendor->vendor_company_name ?? $res->order->vendor->vendor_name ?? '';
        $note = !empty($res->note) ? $res->note : 'Uang muka pembelian' . (!empty($supplierName) ? " supplier " . $supplierName : "");

        $journalEntries = [];

        // 3. Tax Calculation Logic
        if ($res->faktur_accepted == TypeEnum::FAKTUR_ACCEPTED && !empty($res->tax)) {
            $taxes = $this->calculateTaxComponents($res, $nominal);

            foreach ($taxes as $tax) {
                // Adjust DPP based on Tax Type
                if ($tax['posisi'] == 'kredit') {
                    // e.g., PPh 23 (Withholding). We pay less to vendor, or we record debt.
                    // Logic from original code: If Tax Sign is Pemotong (Credit), DPP increases?
                    // Original: $dpp = $dpp + $ppn;
                    $dpp += $tax['nominal'];
                } else {
                    // e.g., PPN (VAT In). We pay tax, so part of nominal is tax, not asset.
                    // Original: $dpp = $dpp - $ppn;
                    $dpp -= $tax['nominal'];
                }

                $journalEntries[] = [
                    'coa_id' => $tax['coa_id'],
                    'posisi' => $tax['posisi'],
                    'nominal'=> $tax['nominal'],
                    'note'   => $note . ' (Tax)'
                ];
            }
        }

        // 4. Debit Entry: Uang Muka Pembelian (Asset)
        $journalEntries[] = [
            'coa_id' => $coaUangMukaPembelian,
            'posisi' => 'debet',
            'nominal'=> $dpp,
            'note'   => $note
        ];

        // 5. Credit Entry: Kas/Bank (Money Out)
        // Checks logic: If PPh withholding involved, Nominal paid might be different or standard accounting applies.
        // Assuming 'Nominal' is the actual cash flow out requested by user.
        $journalEntries[] = [
            'coa_id' => $coaKasBank,
            'posisi' => 'kredit',
            'nominal'=> $nominal,
            'note'   => $note
        ];

        // 6. Validate and Save
        $this->validateAndSaveJournal($journalEntries, $res);
    }

    /**
     * Helper to Calculate Tax Components
     */
    private function calculateTaxComponents($res, $amount): array
    {
        $results = [];
        $objTax = $res->tax;

        $taxList = [];
        if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
            $taxList[] = $objTax;
        } else {
            foreach ($objTax->taxgroup as $group) {
                if ($group->tax) $taxList[] = $group->tax;
            }
        }

        foreach ($taxList as $taxCfg) {
            // Determine Calculation Method
            $func = ($taxCfg->is_dpp_nilai_Lain == 1) ? 'hitungIncludeTaxDppNilaiLain' : 'hitungIncludeTax';
            $calc = Helpers::$func($taxCfg->tax_percentage, $amount);
            $taxNominal = $calc[TypeEnum::PPN];

            // Determine Position
            $posisi = ($taxCfg->tax_sign == VarType::TAX_SIGN_PEMOTONG) ? 'kredit' : 'debet';

            $results[] = [
                'coa_id'  => $taxCfg->purchase_coa_id,
                'posisi'  => $posisi,
                'nominal' => $taxNominal
            ];
        }
        return $results;
    }

    /**
     * Validate Balance and Save to Database
     */
    private function validateAndSaveJournal(array $entries, $dpModel)
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $e) {
            if ($e['posisi'] == 'debet') $totalDebit += $e['nominal'];
            else $totalCredit += $e['nominal'];
        }

        // Tolerance for floating point calculation (1 Rupiah)
        if (abs($totalDebit - $totalCredit) > 1) {
            // THROW EXCEPTION TO TRIGGER ROLLBACK IN store()
            throw new Exception("Jurnal Uang Muka {$dpModel->ref_no} Tidak Balance! Debet: " . number_format($totalDebit) . ", Kredit: " . number_format($totalCredit));
        }

        $jurnalRepo = new JurnalTransaksiRepo(new JurnalTransaksi());

        foreach ($entries as $e) {
            if ($e['nominal'] == 0) continue;

            $jurnalRepo->create([
                'transaction_date'      => $dpModel->downpayment_date,
                'transaction_datetime'  => $dpModel->downpayment_date . " " . date('H:i:s'),
                'created_by'            => $dpModel->created_by,
                'updated_by'            => $dpModel->created_by,
                'transaction_code'      => TransactionsCode::UANG_MUKA_PEMBELIAN,
                'coa_id'                => $e['coa_id'],
                'transaction_id'        => $dpModel->id,
                'transaction_sub_id'    => 0,
                'transaction_no'        => $dpModel->ref_no,
                'transaction_status'    => JurnalStatusEnum::OK,
                'debet'                 => ($e['posisi'] == 'debet') ? $e['nominal'] : 0,
                'kredit'                => ($e['posisi'] == 'kredit') ? $e['nominal'] : 0,
                'note'                  => $e['note'],
                'created_at'            => date("Y-m-d H:i:s"),
                'updated_at'            => date("Y-m-d H:i:s"),
            ]);
        }
    }

    public function deleteAdditional($id)
    {
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::UANG_MUKA_PEMBELIAN, $id);
        PurchaseDownPaymentMeta::where('dp_id', $id)->delete();
    }

    public function deleteData($id)
    {
        DB::beginTransaction();
        try {
            $this->deleteAdditional($id);
            $this->delete($id);
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Delete DP Failed: " . $e->getMessage());
            return false;
        }
    }

    public function getTotalUangMukaByOrderId($idOrder)
    {
        return PurchaseDownPayment::where('order_id', $idOrder)->sum('nominal');
    }

    public static function changeStatusUangMuka($idUangMuka, $statusUangMuka=StatusEnum::SELESAI)
    {
        $instance = new self(new PurchaseDownPayment());
        $instance->update(['downpayment_status' => $statusUangMuka], $idUangMuka);
    }
}