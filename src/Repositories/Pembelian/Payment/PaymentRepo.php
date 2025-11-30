<?php

namespace Icso\Accounting\Repositories\Pembelian\Payment;

use Exception;
use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Pembelian\Pembayaran\PurchasePayment;
use Icso\Accounting\Models\Pembelian\Pembayaran\PurchasePaymentInvoice;
use Icso\Accounting\Models\Pembelian\Retur\PurchaseRetur;
use Icso\Accounting\Models\Tenant\Pembayaran\PurchasePaymentMeta;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Pembelian\Invoice\InvoiceRepo;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentRepo extends ElequentRepository
{
    protected $model;

    public function __construct(PurchasePayment $model)
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
            $query->where('payment_no', 'like', '%' .$search. '%');
        })->orderBy('payment_date','desc')->with(['vendor','payment_method','invoice','invoice.purchaseinvoice','invoiceretur','invoiceretur.retur'])->offset($page)->limit($perpage)->get();
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
            $query->where('payment_no', 'like', '%' .$search. '%');
        })->orderBy('payment_date','desc')->count();
    }

    /**
     * Store method with strict Transaction and Balance Check
     */
    public function store(Request $request, array $other = [])
    {
        $userId = $request->user_id;
        $id = $request->id;

        // Prepare Header Data
        $data = $this->gatherHeaderData($request);

        DB::beginTransaction();
        try {
            // 1. Save Header
            if (empty($id)) {
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['created_by'] = $userId;
                $data['reason'] = '';
                $data['payment_status'] = StatusEnum::SELESAI;
                $res = $this->create($data);
                $paymentId = $res->id;
            } else {
                $this->update($data, $id);
                $paymentId = $id;
                $this->deleteAdditional($paymentId);
            }

            // 2. Save Details (Invoices & Returns)
            // Handle Invoices
            $invoices = is_array($request->invoice) ? $request->invoice : json_decode($request->invoice);
            if (!empty($invoices)) {
                foreach ($invoices as $item) {
                    $item = (object) $item;
                    $this->createPaymentDetail($item, $paymentId, $data['payment_no'], $data['payment_date'], 'invoice');
                    InvoiceRepo::changeStatusInvoice($item->id);
                }
            }

            // Handle Returns
            $returs = is_array($request->retur) ? $request->retur : json_decode($request->retur);
            if (!empty($returs)) {
                foreach ($returs as $item) {
                    $item = (object) $item;
                    $this->createPaymentDetail($item, $paymentId, $data['payment_no'], $data['payment_date'], 'retur');
                    PurchaseRetur::where('id', $item->id)->update(['retur_status' => StatusEnum::SELESAI]);
                }
            }

            // 3. Posting Jurnal (CRITICAL)
            // This will THROW Exception if Debits != Credits
            $this->postingJurnal($paymentId);

            // 4. File Upload
            $this->handleFileUploads($request->file('files'), $paymentId, $userId);

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Payment Store Error: " . $e->getMessage());
            return false;
        }
    }

    private function gatherHeaderData(Request $request)
    {
        $paymentNo = $request->payment_no ?: self::generateCodeTransaction(new PurchasePayment(), KeyNomor::NO_PELUNASAN_PEMBELIAN, 'payment_no', 'payment_date');

        return [
            'payment_date'      => $request->payment_date ? Utility::changeDateFormat($request->payment_date) : date('Y-m-d'),
            'payment_no'        => $paymentNo,
            'note'              => $request->note ?? '',
            'total'             => Utility::remove_commas($request->total),
            'vendor_id'         => $request->vendor_id ?? '0',
            'payment_method_id' => $request->payment_method_id ?? '0',
            'updated_at'        => date('Y-m-d H:i:s'),
            'updated_by'        => $request->user_id
        ];
    }

    private function createPaymentDetail($item, $paymentId, $paymentNo, $paymentDate, $type)
    {
        $isInvoice = ($type === 'invoice');

        $arrDetail = [
            'invoice_no'        => $isInvoice ? $item->invoice_no : $item->retur_no,
            'total_payment'     => Utility::remove_commas($isInvoice ? $item->nominal_paid : $item->total),
            'payment_date'      => $paymentDate,
            'payment_id'        => $paymentId,
            'payment_no'        => $paymentNo,
            'vendor_id'         => $item->vendor_id,
            'invoice_id'        => $isInvoice ? $item->id : 0,
            'retur_id'          => $isInvoice ? 0 : $item->id,
            'jurnal_id'         => 0,

            // Discount & Overpayment only applies to Invoice payments in this context
            'total_discount'    => ($isInvoice && !empty($item->coa_kurang_bayar)) ? Utility::remove_commas($item->total_kurang_bayar) : 0,
            'coa_id_discount'   => ($isInvoice && !empty($item->coa_kurang_bayar)) ? json_encode($item->coa_kurang_bayar) : "",
            'total_overpayment' => ($isInvoice && !empty($item->coa_lebih_bayar)) ? Utility::remove_commas($item->total_lebih_bayar) : 0,
            'coa_id_overpayment'=> ($isInvoice && !empty($item->coa_lebih_bayar)) ? json_encode($item->coa_lebih_bayar) : ""
        ];

        PurchasePaymentInvoice::create($arrDetail);
    }

    public function deleteAdditional($idPayment)
    {
        PurchasePaymentInvoice::where('payment_id', $idPayment)->delete();
        PurchasePaymentMeta::where('payment_id', $idPayment)->delete();
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PELUNASAN_PEMBELIAN, $idPayment);
    }

    /**
     * Refactored Posting Jurnal
     * Calculates all entries first, verifies balance, then saves.
     */
    public function postingJurnal($idPayment)
    {
        // 1. Eager Load Data
        $find = $this->model->with(['payment_method.coa', 'invoice', 'vendor'])->find($idPayment);

        if (!$find) return;

        // 2. Prepare Settings
        $coaUtangUsaha = SettingRepo::getOptionValue(SettingEnum::COA_UTANG_USAHA);
        $coaKasBank = $find->payment_method->coa_id ?? SettingRepo::getOptionValue(SettingEnum::COA_KAS_BANK);

        $journalEntries = [];
        $note = !empty($find->note) ? $find->note : 'Pelunasan Pembelian supplier ' . ($find->vendor->vendor_name ?? '');

        // 3. Calculate Totals from Details
        // We query the details we just saved to ensure consistency
        $details = PurchasePaymentInvoice::where('payment_id', $idPayment)->get();

        $totalAllocatedInvoice = $details->where('invoice_id', '!=', 0)->sum('total_payment');
        $totalAllocatedRetur   = $details->where('retur_id', '!=', 0)->sum('total_payment');
        $totalDiskon           = $details->sum('total_discount');
        $totalLebih            = $details->sum('total_overpayment');

        // 4. Logic for Accounts Payable (Utang Usaha) Debit
        // Logic: AP Debit = (Invoices Paid) - (Returns Applied) + (Discounts Taken) - (Overpayments/Expenses)
        // This formula ensures we reduce the AP by the full amount settled.
        $debitUtangUsaha = ($totalAllocatedInvoice - $totalAllocatedRetur) + $totalDiskon - $totalLebih;

        // Entry A: Debit Utang Usaha
        if ($debitUtangUsaha != 0) {
            $journalEntries[] = [
                'coa_id' => $coaUtangUsaha,
                'posisi' => 'debet',
                'nominal'=> $debitUtangUsaha,
                'note'   => $note
            ];
        }

        // 5. Process Adjustments (Discounts & Overpayments) from Invoice Details
        foreach ($find->invoice as $invDetail) {
            // Discounts (Income/Reduction of AP) -> Credit
            if (!empty($invDetail->coa_id_discount)) {
                $discItems = json_decode($invDetail->coa_id_discount);
                if (is_array($discItems)) {
                    foreach ($discItems as $item) {
                        $item = (object)$item;
                        $journalEntries[] = [
                            'coa_id' => $item->coa_id,
                            'posisi' => 'kredit',
                            'nominal'=> Utility::remove_commas($item->nominal),
                            'note'   => $note . ' (Diskon)'
                        ];
                    }
                }
            }

            // Overpayments (Expense/Asset) -> Debit
            if (!empty($invDetail->coa_id_overpayment)) {
                $overItems = json_decode($invDetail->coa_id_overpayment);
                if (is_array($overItems)) {
                    foreach ($overItems as $item) {
                        $item = (object)$item;
                        $journalEntries[] = [
                            'coa_id' => $item->coa_id,
                            'posisi' => 'debet',
                            'nominal'=> Utility::remove_commas($item->nominal),
                            'note'   => $note . ' (Lebih Bayar)'
                        ];
                    }
                }
            }
        }

        // 6. Entry B: Credit Cash/Bank (The actual money spent)
        // This comes from the Header Total
        $journalEntries[] = [
            'coa_id' => $coaKasBank,
            'posisi' => 'kredit',
            'nominal'=> $find->total,
            'note'   => $note
        ];

        // 7. Validate Balance
        $this->validateAndSaveJournal($journalEntries, $find);
    }

    private function validateAndSaveJournal(array $entries, $paymentModel)
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $e) {
            if ($e['posisi'] == 'debet') $totalDebit += $e['nominal'];
            else $totalCredit += $e['nominal'];
        }

        // 1 Rupiah Tolerance
        if (abs($totalDebit - $totalCredit) > 1) {
            throw new Exception("Jurnal Pelunasan {$paymentModel->payment_no} Tidak Balance! Debet: " . number_format($totalDebit) . ", Kredit: " . number_format($totalCredit));
        }

        $jurnalRepo = new JurnalTransaksiRepo(new JurnalTransaksi());

        foreach ($entries as $e) {
            if ($e['nominal'] == 0) continue;

            $jurnalRepo->create([
                'transaction_date'      => $paymentModel->payment_date,
                'transaction_datetime'  => $paymentModel->payment_date . " " . date('H:i:s'),
                'created_by'            => $paymentModel->created_by,
                'updated_by'            => $paymentModel->created_by,
                'transaction_code'      => TransactionsCode::PELUNASAN_PEMBELIAN,
                'coa_id'                => $e['coa_id'],
                'transaction_id'        => $paymentModel->id,
                'transaction_sub_id'    => 0,
                'transaction_no'        => $paymentModel->payment_no,
                'transaction_status'    => JurnalStatusEnum::OK,
                'debet'                 => ($e['posisi'] == 'debet') ? $e['nominal'] : 0,
                'kredit'                => ($e['posisi'] == 'kredit') ? $e['nominal'] : 0,
                'note'                  => $e['note'],
                'created_at'            => date("Y-m-d H:i:s"),
                'updated_at'            => date("Y-m-d H:i:s"),
            ]);
        }
    }

    private function handleFileUploads($uploadedFiles, $paymentId, $userId)
    {
        if (!empty($uploadedFiles)) {
            $fileUpload = new FileUploadService();
            foreach ($uploadedFiles as $file) {
                $resUpload = $fileUpload->upload($file, tenant(), $userId);
                if ($resUpload) {
                    PurchasePaymentMeta::create([
                        'payment_id' => $paymentId,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload
                    ]);
                }
            }
        }
    }
}