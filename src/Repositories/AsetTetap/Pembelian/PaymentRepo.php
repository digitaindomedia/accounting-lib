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
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\AsetTetap\Penjualan\SalesInvoiceRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Repositories\Utils\SettingRepo;
use Icso\Accounting\Services\FileUploadService;
use Icso\Accounting\Utils\InputType;
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
        // TODO: Implement store() method.
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
                    $this->deleteAdditional($id);
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
                            // Handle each file as needed
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
                return true;
            }
            else {
                return false;
            }
        } catch (\Exception $e){
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function deleteAdditional($idPayment){
        PurchasePaymentMeta::where('payment_id','=',$idPayment)->delete();
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PELUNASAN_PEMBELIAN_ASET_TETAP, $idPayment);
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PELUNASAN_PENJUALAN_ASET_TETAP, $idPayment);
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
                'transaction_code' => TransactionsCode::PELUNASAN_PEMBELIAN,
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
            if(!empty($find->invoice->order)){
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

}
