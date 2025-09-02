<?php

namespace Icso\Accounting\Repositories\AsetTetap\Pembelian;

use Icso\Accounting\Enums\InvoiceStatusEnum;
use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseInvoice;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseInvoiceDp;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseInvoiceMeta;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchaseOrder;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\KeyNomor;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceRepo extends ElequentRepository
{
    protected $model;

    public function __construct(PurchaseInvoice $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = []): mixed
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        // $paymentInvoiceRepo = new PaymentInvoiceRepo(new PurchasePaymentInvoice());
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('invoice_no', 'like', '%' .$search. '%');
            $query->orWhereHas('order', function ($query) use ($search) {
                $query->where('nama_aset', 'like', '%' .$search. '%');
                $query->orWhere('no_aset', 'like', '%' .$search. '%');
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
        })->with(['order','order.aset_tetap_coa', 'order.dari_akun_coa','order.akumulasi_penyusutan_coa','order.penyusutan_coa'])->orderBy('invoice_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = []): int
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('invoice_no', 'like', '%' .$search. '%');
            $query->orWhereHas('order', function ($query) use ($search) {
                $query->where('nama_aset', 'like', '%' .$search. '%');
                $query->orWhere('no_aset', 'like', '%' .$search. '%');
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

    public function store(Request $request, array $other = []): bool
    {
        $id = $request->id;
        $invoiceNo = $request->invoice_no;
        if(empty($receivedNo)){
            $invoiceNo = self::generateCodeTransaction(new PurchaseInvoice(),KeyNomor::NO_INVOICE_PEMBELIAN_ASET_TETAP,'invoice_no','invoice_date');
        }
        $invoiceDate = Utility::changeDateFormat($request->invoice_date);
        $note = !empty($request->note) ? $request->note : '';
        $userId = $request->user_id;
        $orderId = !empty($request->order_id) ? $request->order_id : '0';
        $faktur = !empty($request->faktur) ? $request->faktur : '';
        $tanggalFaktur = !empty($request->tanggal_faktur) ? $request->tanggal_faktur : '';
        $dpp = 0;
        $ppn = 0;
        $total = 0;
        $totalTagihan = !empty($request->total_tagihan) ? Utility::remove_commas($request->total_tagihan) : '0';
        $dueDate = !empty($request->due_date) ? Utility::changeDateFormat($request->due_date): date('Y-m-d');
        $findOrder = PurchaseOrder::where(array('id' => $orderId))->first();
        if(!empty($findOrder)){
            $dpp = $findOrder->dpp;
            $ppn = $findOrder->ppn;
            $total = $findOrder->harga_beli;
        }
        $arrData = array(
            'invoice_date' => $invoiceDate,
            'invoice_no' => $invoiceNo,
            'note' => $note,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
            'order_id' => $orderId,
            'dpp' => $dpp,
            'ppn' => $ppn,
            'total' => $total,
            'total_tagihan' => $totalTagihan,
            'faktur' => $faktur,
            'tanggal_faktur' => $tanggalFaktur,
            'due_date' => $dueDate
        );
        DB::beginTransaction();
        try {
            if (empty($id)) {
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $arrData['created_by'] = $userId;
                $arrData['reason'] = '';
                $arrData['invoice_status'] = InvoiceStatusEnum::BELUM_LUNAS;
                $res = $this->create($arrData);
            } else {
                $res = $this->update($arrData, $id);
            }
            if($res){
                if(!empty($id)){
                    $this->deleteAdditional($id);
                    $idInvoice = $id;
                } else {
                    $idInvoice = $res->id;
                }
                if(!empty($request->dp)){
                    $dps = json_decode(json_encode($request->dp));
                    foreach ($dps as $dp){
                        $arrInvoiceDp = array(
                            'invoice_id' => $idInvoice,
                            'dp_id' => $dp->id
                        );
                        $resDp = PurchaseInvoiceDp::create($arrInvoiceDp);
                        if($resDp){
                            DownPaymentRepo::changeStatus($dp->id,StatusEnum::SELESAI);
                        }
                    }

                }
                $fileUpload = new FileUploadService();
                $uploadedFiles = $request->file('files');
                if(!empty($uploadedFiles)) {
                    if (count($uploadedFiles) > 0) {
                        foreach ($uploadedFiles as $file) {
                            // Handle each file as needed
                            $resUpload = $fileUpload->upload($file, tenant(), $request->user_id);
                            if ($resUpload) {
                                $arrUpload = array(
                                    'invoice_id' => $idInvoice,
                                    'meta_key' => 'upload',
                                    'meta_value' => $resUpload
                                );
                                PurchaseInvoiceMeta::create($arrUpload);
                            }
                        }
                    }
                }
                $this->postingJurnal($idInvoice);
                ReceiveRepo::changeStatusByOrderId($orderId,StatusEnum::SELESAI);
                OrderRepo::changeStatus($orderId,StatusEnum::SELESAI);
                DB::commit();
                return true;
            }
            else {
                return false;
            }
        }
        catch (\Exception $e) {
            // Rollback Transaction
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function deleteAdditional($id)
    {
        $findDp = PurchaseInvoiceDp::where(array('invoice_id' => $id))->with(['downpayment'])->get();
        if(!empty($findDp)) {
            foreach ($findDp as $dp) {
                DownPaymentRepo::changeStatus($dp->dp_id);
            }
        }
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::INVOICE_PEMBELIAN_ASET_TETAP, $id);
        PurchaseInvoiceDp::where(array('invoice_id' => $id))->delete();
        PurchaseInvoiceMeta::where(array('invoice_id' => $id))->delete();
    }

    public function postingJurnal($idInvoice): void
    {
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $coaUtangLainLain = SettingRepo::getOptionValue(SettingEnum::COA_UTANG_LAIN_LAIN);
        $coaBebanDiBayarDiMuka = SettingRepo::getOptionValue(SettingEnum::COA_BEBAN_DIBAYAR_DIMUKA);
        $coaUangMuka = SettingRepo::getOptionValue(SettingEnum::COA_UANG_MUKA_PEMBELIAN_ASET_TETAP);
        $coaPpnMasukan = SettingRepo::getOptionValue(SettingEnum::COA_PPN_MASUKAN);
        $find = $this->findOne($idInvoice,array(),['order']);
        if(!empty($find)) {
            $invDate = $find->invoice_date;
            $invNo = $find->invoice_no;
            $totalUangMuka = 0;
            $findDp = PurchaseInvoiceDp::where(array('invoice_id' => $find->id))->with(['downpayment'])->get();
            if(!empty($findDp)){
                foreach ($findDp as $dp){
                    $uangMuka = $dp->downpayment;
                    $nominalUangMuka = $uangMuka->nominal;
                    $totalUangMuka = $totalUangMuka + $nominalUangMuka;
                    $arrJurnalDebet = array(
                        'transaction_date' => $invDate,
                        'transaction_datetime' => $invDate." ".date('H:i:s'),
                        'created_by' => $find->created_by,
                        'updated_by' => $find->created_by,
                        'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN_ASET_TETAP,
                        'coa_id' => $coaUtangLainLain,
                        'transaction_id' => $find->id,
                        'transaction_sub_id' => $dp->id,
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                        'transaction_no' => $invNo,
                        'transaction_status' => JurnalStatusEnum::OK,
                        'debet' => $nominalUangMuka,
                        'kredit' => 0,
                        'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian',
                    );
                    $jurnalTransaksiRepo->create($arrJurnalDebet);

                    $arrJurnalKredit = array(
                        'transaction_date' => $invDate,
                        'transaction_datetime' => $invDate." ".date('H:i:s'),
                        'created_by' => $find->created_by,
                        'updated_by' => $find->created_by,
                        'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN_ASET_TETAP,
                        'coa_id' => $coaUangMuka,
                        'transaction_id' => $find->id,
                        'transaction_sub_id' => $dp->id,
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                        'transaction_no' => $invNo,
                        'transaction_status' => JurnalStatusEnum::OK,
                        'debet' => 0,
                        'kredit' => $nominalUangMuka,
                        'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian',
                    );
                    $jurnalTransaksiRepo->create($arrJurnalKredit);
                }
            }
            //jurnal debet untuk invoice akun beban dibayar dimuka
            $arrJurnalDebet = array(
                'transaction_date' => $invDate,
                'transaction_datetime' => $invDate." ".date('H:i:s'),
                'created_by' => $find->created_by,
                'updated_by' => $find->created_by,
                'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN_ASET_TETAP,
                'coa_id' => $coaBebanDiBayarDiMuka,
                'transaction_id' => $find->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $invNo,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => $find->dpp,
                'kredit' => 0,
                'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian',
            );
            $jurnalTransaksiRepo->create($arrJurnalDebet);
            //jurnal debet untuk invoice akun ppn masukan
            $arrJurnalDebet = array(
                'transaction_date' => $invDate,
                'transaction_datetime' => $invDate." ".date('H:i:s'),
                'created_by' => $find->created_by,
                'updated_by' => $find->created_by,
                'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN_ASET_TETAP,
                'coa_id' => $coaPpnMasukan,
                'transaction_id' => $find->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $invNo,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => $find->ppn,
                'kredit' => 0,
                'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian',
            );
            $jurnalTransaksiRepo->create($arrJurnalDebet);
            //penjumlahan total tagihan dan total uang muka yang dipakai
            $totalUtangLainLain = $find->total_tagihan+$totalUangMuka;

            //jurnal buat utang lain-lain
            $arrJurnalKredit = array(
                'transaction_date' => $invDate,
                'transaction_datetime' => $invDate." ".date('H:i:s'),
                'created_by' => $find->created_by,
                'updated_by' => $find->created_by,
                'transaction_code' => TransactionsCode::INVOICE_PEMBELIAN_ASET_TETAP,
                'coa_id' => $coaUtangLainLain,
                'transaction_id' => $find->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $invNo,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => 0,
                'kredit' => $totalUtangLainLain,
                'note' => !empty($find->note) ? $find->note : 'Invoice Pembelian',
            );
            $jurnalTransaksiRepo->create($arrJurnalKredit);
        }
    }

    public static function changeStatus($id,$status= StatusEnum::LUNAS)
    {
        $res = PurchaseInvoice::findOrFail($id);
        $res->invoice_status = $status;
        $res->save();
    }
}
