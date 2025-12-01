<?php

namespace Icso\Accounting\Repositories\Penjualan\Downpayment;

use Exception;
use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Enums\TypeEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Penjualan\UangMuka\SalesDownpayment;
use Icso\Accounting\Models\Penjualan\UangMuka\SalesDownPaymentMeta;
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

    public function __construct(SalesDownpayment $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    // ... [Keep getAllDataBy and getAllTotalDataBy as they were] ...
    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        $model = new $this->model;
        return $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%')
                ->orWhere('note', 'like', '%' .$search. '%');
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
        })->with(['order','coa','order.vendor'])->orderBy('downpayment_date','desc')->offset($page)->limit($perpage)->get();
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        $model = new $this->model;
        return $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%')
                ->orWhere('note', 'like', '%' .$search. '%');
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
        })->orderBy('downpayment_date','desc')->count();
    }

    /**
     * Store method with strict Transaction and Balance Check
     */
    public function store(Request $request, array $other = [])
    {
        $id = $request->id;
        $userId = $request->user_id;

        // Prepare Data
        $data = $this->gatherInputData($request);

        DB::beginTransaction();
        try {
            // 1. Save Header
            if (empty($id)) {
                $data['downpayment_status'] = StatusEnum::OPEN;
                $data['reason'] = "";
                $data['document'] = "";
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['created_by'] = $userId;
                $res = $this->create($data);
                $resId = $res->id;
            } else {
                $this->update($data, $id);
                $resId = $id;
                $this->deleteAdditional($resId);
            }

            // 2. Posting Jurnal (CRITICAL)
            // This will THROW Exception if Debits != Credits
            $this->postingJurnal($resId);

            // 3. File Upload
            $this->handleFileUploads($request->file('files'), $resId, $userId);

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Sales DP Store Error: " . $e->getMessage());
            return false;
        }
    }

    private function gatherInputData(Request $request)
    {
        $refNo = $request->ref_no ?: self::generateCodeTransaction(new SalesDownpayment(), KeyNomor::NO_UANG_MUKA_PENJUALAN, 'ref_no', 'downpayment_date');

        return [
            'ref_no'            => $refNo,
            'downpayment_date'  => $request->downpayment_date ? Utility::changeDateFormat($request->downpayment_date) : date("Y-m-d"),
            'nominal'           => Utility::remove_commas($request->nominal),
            'note'              => $request->note,
            'order_id'          => $request->order_id,
            'coa_id'            => $request->coa_id ?? 0,
            'tax_id'            => $request->tax_id ?? 0,
            'tax_percentage'    => $request->tax_percentage,
            'dp_type'           => $request->dp_type,
            'updated_by'        => $request->user_id,
            'updated_at'        => date('Y-m-d H:i:s')
        ];
    }

    public function deleteAdditional($id)
    {
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::UANG_MUKA_PENJUALAN, $id);
        SalesDownPaymentMeta::where('dp_id', $id)->delete();
    }

    /**
     * Refactored Posting Jurnal with Balance Check
     */
    public function postingJurnal($idUangMuka)
    {
        // 1. Eager Load
        $res = $this->model->with(['order.vendor', 'tax.taxgroup.tax'])->find($idUangMuka);

        if (!$res) return;

        // 2. Settings
        $coaUangMukaPenjualan = SettingRepo::getOptionValue(SettingEnum::COA_UANG_MUKA_PENJUALAN);
        $coaKasBank = !empty($res->coa_id) ? $res->coa_id : SettingRepo::getOptionValue(SettingEnum::COA_KAS_BANK);

        $journalEntries = [];
        $nominal = $res->nominal;
        $dpp = $nominal;

        $customerName = "";
        if ($res->order && $res->order->vendor) {
            $customerName = $res->order->vendor->vendor_company_name ?? $res->order->vendor->vendor_name;
        }
        $note = !empty($res->note) ? $res->note : 'Uang muka penjualan' . (!empty($customerName) ? " customer " . $customerName : "");

        // 3. Calculate Tax & Adjust DPP
        $taxes = $this->calculateTaxComponents($res);

        foreach ($taxes as $tax) {
            // Logic for Sales DP:
            // If Tax Sign is PEMOTONG (Withholding/PPh) -> Debit. (Reduces Cash received usually, but here Nominal is fixed, so it increases DPP Asset?)
            // If Tax Sign is Normal (VAT/PPN) -> Credit. (Liability).

            if ($tax['tax_sign'] == VarType::TAX_SIGN_PEMOTONG) {
                // Posisi DEBET
                $dpp += $tax['nominal']; // Original Logic: $dpp = $dpp + $ppn
                $journalEntries[] = [
                    'coa_id' => $tax['coa_id'],
                    'posisi' => 'debet',
                    'nominal'=> $tax['nominal'],
                    'note'   => $note . ' (Tax)'
                ];
            } else {
                // Posisi KREDIT (Standard VAT)
                $dpp -= $tax['nominal']; // Original Logic: $dpp = $dpp - $ppn
                $journalEntries[] = [
                    'coa_id' => $tax['coa_id'],
                    'posisi' => 'kredit',
                    'nominal'=> $tax['nominal'],
                    'note'   => $note . ' (Tax)'
                ];
            }
        }

        // 4. Entry A: Debit Cash/Bank (Money In)
        $journalEntries[] = [
            'coa_id' => $coaKasBank,
            'posisi' => 'debet',
            'nominal'=> $nominal,
            'note'   => $note
        ];

        // 5. Entry B: Credit Sales Downpayment Liability
        $journalEntries[] = [
            'coa_id' => $coaUangMukaPenjualan,
            'posisi' => 'kredit',
            'nominal'=> $dpp,
            'note'   => $note
        ];

        // 6. Validate & Save
        $this->validateAndSaveJournal($journalEntries, $res);
    }

    /**
     * Helper to Calculate Tax Components
     */
    private function calculateTaxComponents($res): array
    {
        $results = [];
        $objTax = $res->tax;

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
            // Calculation
            $calcFunc = ($res->tax_type == TypeEnum::TAX_TYPE_INCLUDE) ? 'hitungIncludeTax' : 'hitungTaxDpp';
            // Note: Original code used 'hitungIncludeTax' directly for Single.
            // Using logic from original code:

            $nominalTax = 0;
            if ($objTax->tax_type == VarType::TAX_TYPE_SINGLE) {
                // Original strictly used hitungIncludeTax for single tax in loop
                // Assuming strictly Include based on original snippet logic:
                $calc = Helpers::hitungIncludeTax($taxCfg->tax_percentage, $res->nominal);
                $nominalTax = $calc[TypeEnum::PPN];
            } else {
                // Group Logic from original
                $pembagi = ($taxCfg->tax_percentage + 100) / 100;
                $subtotal = $res->nominal / $pembagi;
                $nominalTax = ($taxCfg->tax_percentage / 100) * $subtotal;
            }

            $results[] = [
                'coa_id'    => $taxCfg->sales_coa_id, // Note: Use sales_coa_id for Sales
                'nominal'   => $nominalTax,
                'tax_sign'  => $taxCfg->tax_sign
            ];
        }
        return $results;
    }

    private function validateAndSaveJournal(array $entries, $dpModel)
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $e) {
            if ($e['posisi'] == 'debet') $totalDebit += $e['nominal'];
            else $totalCredit += $e['nominal'];
        }

        // Tolerance 1 Rupiah
        if (abs($totalDebit - $totalCredit) > 1) {
            throw new Exception("Jurnal Sales DP {$dpModel->ref_no} Tidak Balance! Debet: " . number_format($totalDebit) . ", Kredit: " . number_format($totalCredit));
        }

        $jurnalRepo = new JurnalTransaksiRepo(new JurnalTransaksi());

        foreach ($entries as $e) {
            if ($e['nominal'] == 0) continue;

            $jurnalRepo->create([
                'transaction_date'      => $dpModel->downpayment_date,
                'transaction_datetime'  => $dpModel->downpayment_date . " " . date('H:i:s'),
                'created_by'            => $dpModel->created_by,
                'updated_by'            => $dpModel->created_by,
                'transaction_code'      => TransactionsCode::UANG_MUKA_PENJUALAN,
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

    private function handleFileUploads($uploadedFiles, $resId, $userId)
    {
        if (!empty($uploadedFiles)) {
            $fileUpload = new FileUploadService();
            foreach ($uploadedFiles as $file) {
                $resUpload = $fileUpload->upload($file, tenant(), $userId);
                if ($resUpload) {
                    SalesDownPaymentMeta::create([
                        'dp_id' => $resId,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload
                    ]);
                }
            }
        }
    }

    public function getTotalUangMukaByOrderId($idOrder)
    {
        return SalesDownpayment::where('order_id', $idOrder)->sum('nominal');
    }

    public static function changeStatusUangMuka($idUangMuka, $statusUangMuka=StatusEnum::SELESAI)
    {
        $instance = new self(new SalesDownpayment());
        $instance->update(['downpayment_status' => $statusUangMuka], $idUangMuka);
    }
}