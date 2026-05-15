<?php

namespace Icso\Accounting\Repositories\AsetTetap\Pembelian;

use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseInvoice;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchasePayment;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchasePaymentMeta;
use Icso\Accounting\Models\AsetTetap\Penjualan\SalesInvoice;
use Icso\Accounting\Models\Pembelian\Pembayaran\PurchasePayment as RegularPurchasePayment;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\AsetTetap\Penjualan\SalesInvoiceRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Services\ActivityLogService;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\InputType;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\RequestAuditHelper;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentRepo extends ElequentRepository
{
    protected $model;
    protected ActivityLogService $activityLog;

    public function __construct(PurchasePayment $model, ActivityLogService $activityLog)
    {
        parent::__construct($model);
        $this->model = $model;
        $this->activityLog = $activityLog;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = []): mixed
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        // $paymentInvoiceRepo = new PaymentInvoiceRepo(new PurchasePaymentInvoice());
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('payment_no', 'like', '%' .$search. '%');
            $query->orWhereHas('invoice', function ($query) use ($search) {
                $query->where('invoice_no', 'like', '%' .$search. '%');
            });
            $query->orWhereHas('payment_method', function ($query) use ($search) {
                $query->where('payment_name', 'like', '%' .$search. '%');
            });
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
        })->with(['invoice','invoice.order','payment_method','sales_invoice','sales_invoice.asettetap'])->orderBy('payment_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = []): int
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('payment_no', 'like', '%' .$search. '%');
            $query->orWhereHas('invoice', function ($query) use ($search) {
                $query->where('invoice_no', 'like', '%' .$search. '%');
            });
            $query->orWhereHas('payment_method', function ($query) use ($search) {
                $query->where('payment_name', 'like', '%' .$search. '%');
            });
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
        })->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        $paymentMethod = $request->payment_method_id;
        $paymentDate = !empty($request->payment_date) ? Utility::changeDateFormat($request->payment_date) : date('Y-m-d');
        $paymentNo = $request->payment_no;
        if (empty($paymentNo)) {
            if($request->payment_type == InputType::PURCHASE){
                $paymentNo = self::generateCodeTransaction(new PurchasePayment(), KeyNomor::NO_PELUNASAN_PEMBELIAN_ASET_TETAP, 'payment_no', 'payment_date');
            } else {
                $paymentNo = self::generateCodeTransaction(new PurchasePayment(), KeyNomor::NO_SALES_PAYMENT_ASET_TETAP, 'payment_no', 'payment_date');
            }

        }
        $userId = $request->user_id;
        $invoiceId = $request->invoice_id;
        $note = !empty($request->note) ? $request->note : '';
        $total = Utility::remove_commas($request->total);
        $id = $request->id;
        $oldData = null;
        if (!empty($id)) {
            $oldData = $this->findOne($id, [], [
                'payment_method',
                'sales_invoice',
                'sales_invoice.asettetap',
                'invoice',
                'invoice.order',
                'invoice.order.aset_tetap_coa',
                'invoice.order.dari_akun_coa',
                'invoice.order.akumulasi_penyusutan_coa',
                'invoice.order.penyusutan_coa'
            ])?->toArray();
        }

        $arrData = array(
            'payment_date' => $paymentDate,
            'payment_no' => $paymentNo,
            'note' => $note,
            'total' => $total,
            'invoice_id' => $invoiceId,
            'payment_method_id' => !empty($paymentMethod) ? $paymentMethod : '0',
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId,
            'payment_type' => $request->payment_type
        );
        DB::beginTransaction();
        try {
            if (empty($id)) {
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $arrData['created_by'] = $userId;
                $arrData['reason'] = '';
                $arrData['payment_status'] = StatusEnum::SELESAI;
                $res = $this->create($arrData);
            } else {
                $res = $this->update($arrData, $id);
            }
            if ($res) {
                if (!empty($id)) {
                    $idPayment = $id;
                    $this->deleteAdditional($id, $oldData['payment_no'] ?? null);
                } else {
                    $idPayment = $res->id;
                }

                if($request->payment_type == InputType::PURCHASE) {
                    $findInvoice = PurchaseInvoice::where('id',$invoiceId)->first();
                    if($findInvoice){
                        $totalPayment = $this->getTotalPaymentByInvoice($invoiceId,$idPayment) + $total;
                        if($totalPayment == $findInvoice->total_tagihan){
                            InvoiceRepo::changeStatus($invoiceId);
                        } else {
                            InvoiceRepo::changeStatus($invoiceId,StatusEnum::BELUM_LUNAS);
                        }
                    }
                    $this->postingJurnalPelunasanPembelian($idPayment);
                } else {
                    $findSalesInvoice = SalesInvoice::where('id',$invoiceId)->first();
                    if($findSalesInvoice){
                        $totalPayment = SalesInvoiceRepo::getTotalPayment($invoiceId,$idPayment) + $total;
                        if($totalPayment == $findSalesInvoice->price){
                            SalesInvoiceRepo::changeStatus($invoiceId);
                        } else {
                            SalesInvoiceRepo::changeStatus($invoiceId,StatusEnum::BELUM_LUNAS);
                        }
                    }
                    $this->postingJurnalPelunasanPenjualan($idPayment);
                }
                $fileUpload = new FileUploadService();
                $uploadedFiles = $request->file('files');
                if(!empty($uploadedFiles)) {
                    if (count($uploadedFiles) > 0) {
                        foreach ($uploadedFiles as $file) {
                            $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                            if ($resUpload) {
                                $arrUpload = array(
                                    'payment_id' => $idPayment,
                                    'meta_key' => 'upload',
                                    'meta_value' => $resUpload
                                );
                                PurchasePaymentMeta::create($arrUpload);
                            }
                        }
                    }
                }
                DB::commit();

                $this->activityLog->log([
                    'user_id' => $userId,
                    'action' => empty($id)
                        ? 'Tambah data pembayaran pembelian aset tetap dengan nomor ' . $paymentNo
                        : 'Edit data pembayaran pembelian aset tetap dengan nomor ' . $paymentNo,
                    'model_type' => PurchasePayment::class,
                    'model_id' => $idPayment,
                    'old_values' => $oldData,
                    'new_values' => $this->findOne($idPayment, [], [
                        'payment_method',
                        'sales_invoice',
                        'sales_invoice.asettetap',
                        'invoice',
                        'invoice.order',
                        'invoice.order.aset_tetap_coa',
                        'invoice.order.dari_akun_coa',
                        'invoice.order.akumulasi_penyusutan_coa',
                        'invoice.order.penyusutan_coa'
                    ])?->toArray(),
                    'request_payload' => RequestAuditHelper::sanitize($request),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return true;
            }
            else {
                return false;
            }
        } catch (\Exception $e){
            Log::error('[AsetTetap\\Pembelian\\PaymentRepo][store] ' . $e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function deleteAdditional($idPayment, ?string $previousPaymentNo = null){
        $paymentNos = $this->getPaymentNosForJournalCleanup((int) $idPayment, $previousPaymentNo);

        PurchasePaymentMeta::where('payment_id','=',$idPayment)->delete();
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PELUNASAN_PEMBELIAN_ASET_TETAP, $idPayment);
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PELUNASAN_PENJUALAN_ASET_TETAP, $idPayment);
        $this->deleteLegacyPembelianJournal((int) $idPayment, $paymentNos);
    }

    private function getPaymentNosForJournalCleanup(int $idPayment, ?string $previousPaymentNo = null): array
    {
        $paymentNos = [];
        if (!empty($previousPaymentNo)) {
            $paymentNos[] = $previousPaymentNo;
        }

        $currentPaymentNo = PurchasePayment::where('id', $idPayment)->value('payment_no');
        if (!empty($currentPaymentNo)) {
            $paymentNos[] = $currentPaymentNo;
        }

        return array_values(array_unique($paymentNos));
    }

    private function deleteLegacyPembelianJournal(int $idPayment, array $paymentNos): void
    {
        if (count($paymentNos) === 0) {
            return;
        }

        $regularPaymentNos = RegularPurchasePayment::where('id', $idPayment)
            ->whereIn('payment_no', $paymentNos)
            ->pluck('payment_no')
            ->toArray();
        $safePaymentNos = array_values(array_diff($paymentNos, $regularPaymentNos));

        if (count($safePaymentNos) === 0) {
            return;
        }

        JurnalTransaksi::where('transaction_code', TransactionsCode::PELUNASAN_PEMBELIAN)
            ->where('transaction_id', $idPayment)
            ->whereIn('transaction_no', $safePaymentNos)
            ->delete();
    }

    public function postingJurnalPelunasanPembelian($idPayment)
    {
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $find = $this->findOne($idPayment, array(), ['payment_method','payment_method.coa','invoice','invoice.order']);
        if(!empty($find)){
            $coaUtangLainLain = SettingRepo::getOptionValue(SettingEnum::COA_UTANG_LAIN_LAIN);
            $coaKasBank = SettingRepo::getOptionValue(SettingEnum::COA_KAS_BANK);
            if(!empty($find->payment_method)){
                $coaKasBank = $find->payment_method->coa_id;
            }
            $namaAset = "";
            if(!empty($find->invoice->order)){
                $namaAset = " dengan nama aset ".$find->invoice->order->nama_aset;
            }
            $arrJurnalDebet = array(
                'transaction_date' => $find->payment_date,
                'transaction_datetime' => $find->payment_date." ".date('H:i:s'),
                'created_by' => $find->created_by,
                'updated_by' => $find->created_by,
                'transaction_code' => TransactionsCode::PELUNASAN_PEMBELIAN_ASET_TETAP,
                'coa_id' => $coaUtangLainLain,
                'transaction_id' => $find->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $find->payment_no,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => $find->total,
                'kredit' => 0,
                'note' => !empty($find->note) ? $find->note : 'Pelunasan Pembelian Aset Tetap'.$namaAset,
            );
            $jurnalTransaksiRepo->create($arrJurnalDebet);
            $arrJurnalKredit = array(
                'transaction_date' => $find->payment_date,
                'transaction_datetime' => $find->payment_date." ".date('H:i:s'),
                'created_by' => $find->created_by,
                'updated_by' => $find->created_by,
                'transaction_code' => TransactionsCode::PELUNASAN_PEMBELIAN_ASET_TETAP,
                'coa_id' => $coaKasBank,
                'transaction_id' => $find->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $find->payment_no,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => 0,
                'kredit' => $find->total,
                'note' => !empty($find->note) ? $find->note : 'Pelunasan Pembelian Aset Tetap'.$namaAset,
            );
            $jurnalTransaksiRepo->create($arrJurnalKredit);
        }

    }

    public function postingJurnalPelunasanPenjualan($idPayment)
    {
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $find = $this->findOne($idPayment, array(), ['payment_method','payment_method.coa','sales_invoice','sales_invoice.asettetap']);
        if(!empty($find)){
            $coaPiutangLainLain = SettingRepo::getOptionValue(SettingEnum::COA_PIUTANG_LAIN_LAIN);
            $coaKasBank = SettingRepo::getOptionValue(SettingEnum::COA_KAS_BANK);
            if(!empty($find->payment_method)){
                $coaKasBank = $find->payment_method->coa_id;
            }
            $namaAset = "";
            if(!empty($find->sales_invoice->asettetap)){
                $namaAset = " dengan nama aset ".$find->sales_invoice->asettetap->nama_aset;
            }
            $arrJurnalDebet = array(
                'transaction_date' => $find->payment_date,
                'transaction_datetime' => $find->payment_date." ".date('H:i:s'),
                'created_by' => $find->created_by,
                'updated_by' => $find->created_by,
                'transaction_code' => TransactionsCode::PELUNASAN_PENJUALAN_ASET_TETAP,
                'coa_id' => $coaKasBank,
                'transaction_id' => $find->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $find->payment_no,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => $find->total,
                'kredit' => 0,
                'note' => !empty($find->note) ? $find->note : 'Pelunasan Penjualan Aset Tetap '.$namaAset,
            );
            $jurnalTransaksiRepo->create($arrJurnalDebet);
            $arrJurnalKredit = array(
                'transaction_date' => $find->payment_date,
                'transaction_datetime' => $find->payment_date." ".date('H:i:s'),
                'created_by' => $find->created_by,
                'updated_by' => $find->created_by,
                'transaction_code' => TransactionsCode::PELUNASAN_PENJUALAN_ASET_TETAP,
                'coa_id' => $coaPiutangLainLain,
                'transaction_id' => $find->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $find->payment_no,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => 0,
                'kredit' => $find->total,
                'note' => !empty($find->note) ? $find->note : 'Pelunasan Penjualan Aset Tetap '.$namaAset,
            );
            $jurnalTransaksiRepo->create($arrJurnalKredit);
        }

    }

    public function getTotalPaymentByInvoice($invoiceId, $idPayment='')
    {
        $query = PurchasePayment::where([['invoice_id',$invoiceId],['payment_type', InputType::PURCHASE]]);
        if(!empty($idPayment)){
            $query->where('id', '!=', $idPayment);
        }
        $total = $query->sum('total');
        return $total;
    }

    private function syncInvoiceStatusAfterDelete(PurchasePayment $payment): void
    {
        if (empty($payment->invoice_id)) {
            return;
        }

        if ($payment->payment_type === InputType::PURCHASE) {
            $invoice = PurchaseInvoice::where('id', $payment->invoice_id)->first();
            if (empty($invoice)) {
                return;
            }

            $remainingPayment = $this->getTotalPaymentByInvoice($payment->invoice_id, $payment->id);
            $status = ((float) $remainingPayment >= (float) $invoice->total_tagihan)
                ? StatusEnum::LUNAS
                : StatusEnum::BELUM_LUNAS;
            InvoiceRepo::changeStatus($payment->invoice_id, $status);
            return;
        }

        if ($payment->payment_type === InputType::SALES) {
            $salesInvoice = SalesInvoice::where('id', $payment->invoice_id)->first();
            if (empty($salesInvoice)) {
                return;
            }

            $remainingPayment = SalesInvoiceRepo::getTotalPayment($payment->invoice_id, $payment->id);
            $status = ((float) $remainingPayment >= (float) $salesInvoice->price)
                ? StatusEnum::LUNAS
                : StatusEnum::BELUM_LUNAS;
            SalesInvoiceRepo::changeStatus($payment->invoice_id, $status);
        }
    }

    public function destroy(int $id, int $userId): bool
    {
        $payment = $this->findOne($id, [], [
            'payment_method',
            'sales_invoice',
            'sales_invoice.asettetap',
            'invoice',
            'invoice.order',
            'invoice.order.aset_tetap_coa',
            'invoice.order.dari_akun_coa',
            'invoice.order.akumulasi_penyusutan_coa',
            'invoice.order.penyusutan_coa'
        ]);
        if (!$payment) {
            return false;
        }

        $oldData = $payment->toArray();

        DB::beginTransaction();
        try {
            $this->deleteAdditional($id, $payment->payment_no);
            $this->syncInvoiceStatusAfterDelete($payment);
            parent::delete($id);
            DB::commit();

            $paymentNo = $oldData['payment_no'] ?? '';
            $this->activityLog->log([
                'user_id' => $userId,
                'action' => 'Hapus data pembayaran pembelian aset tetap dengan nomor ' . $paymentNo,
                'model_type' => PurchasePayment::class,
                'model_id' => $id,
                'old_values' => $oldData,
                'new_values' => null,
                'request_payload' => RequestAuditHelper::sanitize(request()),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[AsetTetap\\Pembelian\\PaymentRepo][destroy] ' . $e->getMessage());
            return false;
        }
    }

}
