<?php

namespace Icso\Accounting\Repositories\Penjualan\Payment;

use Exception;
use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPayment;
use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPaymentInvoice;
use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPaymentMeta;
use Icso\Accounting\Models\Penjualan\Retur\SalesRetur;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Penjualan\Invoice\InvoiceRepo;
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

    public function __construct(SalesPayment $model)
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
            $query->where('payment_no', 'like', '%' .$search. '%');
        })->orderBy('payment_date','desc')->with(['vendor','payment_method','invoice','invoice.salesinvoice','invoiceretur','invoiceretur.retur'])->offset($page)->limit($perpage)->get();
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
            $query->where('payment_no', 'like', '%' .$search. '%');
        })->orderBy('payment_date','desc')->count();
    }

    /**
     * Store method with Strict Transaction and Balance Check
     */
    public function store(Request $request, array $other = [])
    {
        $id = $request->id;
        $userId = $request->user_id;

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

            // 2. Process Details (Invoices)
            $invoices = is_array($request->invoice) ? $request->invoice : json_decode(json_encode($request->invoice));
            if (!empty($invoices)) {
                foreach ($invoices as $item) {
                    $item = (object)$item;
                    $this->createPaymentDetail($item, $paymentId, $data['payment_no'], $data['payment_date'], 'invoice');
                    InvoiceRepo::changeStatusInvoice($item->id);
                }
            }

            // 3. Process Details (Returns)
            $returs = is_array($request->retur) ? $request->retur : json_decode(json_encode($request->retur));
            if (!empty($returs)) {
                foreach ($returs as $item) {
                    $item = (object)$item;
                    $this->createPaymentDetail($item, $paymentId, $data['payment_no'], $data['payment_date'], 'retur');
                    SalesRetur::where('id', $item->id)->update(['retur_status' => StatusEnum::SELESAI]);
                }
            }

            // 4. Posting Jurnal (CRITICAL)
            // Throws Exception if Unbalanced
            $this->postingJurnal($paymentId);

            // 5. File Upload
            $this->handleFileUploads($request->file('files'), $paymentId, $userId);

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Sales Payment Store Error: " . $e->getMessage());
            return false;
        }
    }

    private function gatherHeaderData(Request $request)
    {
        $paymentNo = $request->payment_no ?: self::generateCodeTransaction(new SalesPayment(), KeyNomor::NO_PELUNASAN_PENJUALAN, 'payment_no', 'payment_date');

        return [
            'payment_date'      => $request->payment_date ? Utility::changeDateFormat($request->payment_date) : date('Y-m-d'),
            'payment_no'        => $paymentNo,
            'note'              => $request->note ?? '',
            'total'             => Utility::remove_commas($request->total),
            'vendor_id'         => $request->vendor_id ?? 0,
            'payment_method_id' => $request->payment_method_id ?? 0,
            'updated_at'        => date('Y-m-d H:i:s'),
            'updated_by'        => $request->user_id
        ];
    }

    private function createPaymentDetail($item, $paymentId, $paymentNo, $paymentDate, $type)
    {
        $isInvoice = ($type === 'invoice');

        // Prepare safe values
        $totalDiscount = 0;
        $coaDiscount = "";
        $totalOver = 0;
        $coaOver = "";

        if ($isInvoice) {
            if (!empty($item->coa_kurang_bayar)) {
                $totalDiscount = Utility::remove_commas($item->total_kurang_bayar);
                $coaDiscount = json_encode($item->coa_kurang_bayar);
            }
            if (!empty($item->coa_lebih_bayar)) {
                $totalOver = Utility::remove_commas($item->total_lebih_bayar);
                $coaOver = json_encode($item->coa_lebih_bayar);
            }
        }

        $arrDetail = [
            'invoice_no'        => $isInvoice ? $item->invoice_no : $item->retur_no,
            'total_payment'     => Utility::remove_commas($isInvoice ? $item->nominal_paid : $item->total),
            'payment_date'      => $paymentDate,
            'total_discount'    => $totalDiscount,
            'coa_id_discount'   => $coaDiscount,
            'total_overpayment' => $totalOver,
            'coa_id_overpayment'=> $coaOver,
            'invoice_id'        => $isInvoice ? $item->id : 0,
            'retur_id'          => $isInvoice ? 0 : $item->id,
            'payment_id'        => $paymentId,
            'jurnal_id'         => 0,
            'vendor_id'         => $item->vendor_id,
            'payment_no'        => $paymentNo,
        ];

        SalesPaymentInvoice::create($arrDetail);
    }

    public function deleteAdditional($idPayment)
    {
        SalesPaymentInvoice::where('payment_id', $idPayment)->delete();
        SalesPaymentMeta::where('payment_id', $idPayment)->delete();
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PELUNASAN_PENJUALAN, $idPayment);
    }

    /**
     * Refactored Posting Jurnal with Balance Check
     */
    public function postingJurnal($idPayment)
    {
        // 1. Eager Load
        $find = $this->model->with(['payment_method.coa', 'invoice', 'vendor'])->find($idPayment);

        if (!$find) return;

        // 2. Settings
        $coaPiutangUsaha = SettingRepo::getOptionValue(SettingEnum::COA_PIUTANG_USAHA);
        $coaKasBank = $find->payment_method->coa_id ?? SettingRepo::getOptionValue(SettingEnum::COA_KAS_BANK);

        $journalEntries = [];
        $note = !empty($find->note) ? $find->note : 'Pelunasan penjualan customer ' . ($find->vendor->vendor_name ?? '');

        // 3. Calculate Totals from Allocation Details
        // We re-query the details to ensure we use exactly what was saved.
        $details = SalesPaymentInvoice::where('payment_id', $idPayment)->get();

        // Sums
        $totalAllocatedToInvoices = $details->where('invoice_id', '!=', 0)->sum('total_payment');
        $totalAllocatedToRetur    = $details->where('retur_id', '!=', 0)->sum('total_payment');
        $totalDiskon              = $details->sum('total_discount');
        $totalLebihBayar          = $details->sum('total_overpayment');

        // 4. Calculate Credit to Piutang Usaha
        // Logic:
        // Cash Received (Header Total) = (Invoices Paid - Returns Applied) - Discounts + Overpayments?
        // Let's use the Standard Accounting Equation for Receipt:
        // Dr Cash (Money In)
        // Dr Discount (Expense)
        // Dr Return (If return is reducing cash? No, Return reduces Piutang separately usually).
        // Cr Piutang (Total Invoice Amount Settled)
        // Cr Overpayment (Liability)

        // From original code logic:
        // $totalPiutangUsaha = (($totalPiutangUsaha - $totalRetur) + $totalDiskon) - $totalLebih;
        // This calculates the Net Credit to Piutang.

        $creditPiutang = ($totalAllocatedToInvoices - $totalAllocatedToRetur) + $totalDiskon - $totalLebihBayar;

        // Entry A: Debit Cash/Bank (Total Header)
        $journalEntries[] = [
            'coa_id' => $coaKasBank,
            'posisi' => 'debet',
            'nominal'=> $find->total,
            'note'   => $note
        ];

        // Entry B: Debit Discounts
        foreach ($find->invoice as $invDetail) {
            if (!empty($invDetail->coa_id_discount)) {
                $discItems = json_decode($invDetail->coa_id_discount);
                if (is_array($discItems)) {
                    foreach ($discItems as $item) {
                        $item = (object)$item;
                        $journalEntries[] = [
                            'coa_id' => $item->coa_id,
                            'posisi' => 'debet',
                            'nominal'=> Utility::remove_commas($item->nominal),
                            'note'   => $note . ' (Diskon)'
                        ];
                    }
                }
            }

            // Entry C: Credit Overpayment (Lebih Bayar)
            if (!empty($invDetail->coa_id_overpayment)) {
                $overItems = json_decode($invDetail->coa_id_overpayment);
                if (is_array($overItems)) {
                    foreach ($overItems as $item) {
                        $item = (object)$item;
                        $journalEntries[] = [
                            'coa_id' => $item->coa_id,
                            'posisi' => 'kredit',
                            'nominal'=> Utility::remove_commas($item->nominal),
                            'note'   => $note . ' (Lebih Bayar)'
                        ];
                    }
                }
            }
        }

        // Entry D: Credit Piutang Usaha
        if ($creditPiutang != 0) {
            $journalEntries[] = [
                'coa_id' => $coaPiutangUsaha,
                'posisi' => 'kredit',
                'nominal'=> $creditPiutang,
                'note'   => $note
            ];
        }

        // 5. Validate & Save
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

        // Tolerance 1 Rupiah
        if (abs($totalDebit - $totalCredit) > 1) {
            throw new Exception("Jurnal Sales Payment {$paymentModel->payment_no} Tidak Balance! Debet: " . number_format($totalDebit) . ", Kredit: " . number_format($totalCredit));
        }

        $jurnalRepo = new JurnalTransaksiRepo(new JurnalTransaksi());

        foreach ($entries as $e) {
            if ($e['nominal'] == 0) continue;

            $jurnalRepo->create([
                'transaction_date'      => $paymentModel->payment_date,
                'transaction_datetime'  => $paymentModel->payment_date . " " . date('H:i:s'),
                'created_by'            => $paymentModel->created_by,
                'updated_by'            => $paymentModel->created_by,
                'transaction_code'      => TransactionsCode::PELUNASAN_PENJUALAN,
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
                    SalesPaymentMeta::create([
                        'payment_id' => $paymentId,
                        'meta_key' => 'upload',
                        'meta_value' => $resUpload
                    ]);
                }
            }
        }
    }
}