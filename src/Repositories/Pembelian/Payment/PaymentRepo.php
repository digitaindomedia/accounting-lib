<?php

namespace Icso\Accounting\Repositories\Pembelian\Payment;


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

    public function getAllDataBy($search, $page, $perpage, array $where = []): mixed
    {
        // TODO: Implement getAllDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    if($metod == 'whereBetween'){
                        $que->$metod($item['value']['field'],$item['value']['value']);
                    }
                    else {
                        $que->$metod($item['value']);
                    }
                }
            });
        })->when(!empty($search), function ($query) use($search){
            $query->where('payment_no', 'like', '%' .$search. '%');
        })->orderBy('payment_date','desc')->with(['vendor','payment_method','invoice','invoice.purchaseinvoice','invoiceretur','invoiceretur.retur'])->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = []): int
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($where), function ($query) use($where){
            $query->where(function ($que) use($where){
                foreach ($where as $item){
                    $metod = $item['method'];
                    if($metod == 'whereBetween'){
                        $que->$metod($item['value']['field'],$item['value']['value']);
                    }
                    else {
                        $que->$metod($item['value']);
                    }
                }
            });
        })->when(!empty($search), function ($query) use($search){
            $query->where('payment_no', 'like', '%' .$search. '%');
        })->orderBy('payment_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.

        $vendor = !empty($request->vendor_id) ? $request->vendor_id : '';
        $paymentMethod = $request->payment_method_id;
        $paymentDate = !empty($request->payment_date) ? Utility::changeDateFormat($request->payment_date) : date('Y-m-d');
        $paymentNo = $request->payment_no;
        if(empty($paymentNo)){
            $paymentNo = self::generateCodeTransaction(new PurchasePayment(),KeyNomor::NO_PELUNASAN_PEMBELIAN,'payment_no','payment_date');
        }
        $userId = $request->user_id;
        $note = !empty($request->note) ? $request->note : '';
        $total = $request->total;
        $id = $request->id;

        $arrData = array(
            'payment_date' => $paymentDate,
            'payment_no' => $paymentNo,
            'note' => $note,
            'total' => $total,
            'vendor_id' => !empty($vendor) ? $vendor : '0',
            'payment_method_id' => !empty($paymentMethod) ? $paymentMethod : '0',
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $userId
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
            if($res){
                if(!empty($id)){
                    $idPayment = $id;
                    $this->deleteAdditional($id);
                } else {
                    $idPayment = $res->id;
                }
                if(!empty($request->invoice)){
                    $invoices = json_decode(json_encode($request->invoice));
                    if(!empty($invoices)){
                        foreach ($invoices as $key => $item){
                            $arrInvoice = array(
                                'invoice_no' => $item->invoice_no,
                                'total_payment' => Utility::remove_commas($item->nominal_paid),
                                'payment_date' => $paymentDate,
                                'total_discount' => !empty($item->coa_kurang_bayar) ? Utility::remove_commas($item->total_kurang_bayar) : 0,
                                'coa_id_discount' => !empty($item->coa_kurang_bayar) ? json_encode($item->coa_kurang_bayar) : "",
                                'invoice_id' => $item->id,
                                'payment_id' => $idPayment,
                                'jurnal_id' => 0,
                                'vendor_id' => $item->vendor_id,
                                'retur_id' => 0,
                                'payment_no' => $paymentNo,
                                'total_overpayment' => !empty($item->coa_lebih_bayar) ? Utility::remove_commas($item->total_lebih_bayar) : 0,
                                'coa_id_overpayment' => !empty($item->coa_lebih_bayar) ? json_encode($item->coa_lebih_bayar) : ""
                            );
                            PurchasePaymentInvoice::create($arrInvoice);
                            InvoiceRepo::changeStatusInvoice($item->id);
                        }
                    }
                }

                if(!empty($request->retur)) {
                    $returs = json_decode(json_encode($request->retur));
                    if (!empty($returs)) {
                        foreach ($returs as $item) {
                            $arrRetur = array(
                                'invoice_no' => $item->retur_no,
                                'total_payment' => Utility::remove_commas($item->total),
                                'payment_date' => $paymentDate,
                                'total_discount' => 0,
                                'coa_id_discount' => "",
                                'invoice_id' => 0,
                                'payment_id' => $idPayment,
                                'jurnal_id' => 0,
                                'vendor_id' => $item->vendor_id,
                                'retur_id' => $item->id,
                                'payment_no' => $paymentNo,
                                'total_overpayment' => 0,
                                'coa_id_overpayment' => ""
                            );
                            PurchasePaymentInvoice::create($arrRetur);
                            PurchaseRetur::where(array('id' => $item->id))->update(array('retur_status' => StatusEnum::SELESAI));
                        }
                    }
                }
                $this->postingJurnal($idPayment);
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

        }
        catch (\Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            return false;
        }
    }

    public function deleteAdditional($idPayment){
        PurchasePaymentInvoice::where('payment_id','=',$idPayment)->delete();
        PurchasePaymentMeta::where('payment_id','=',$idPayment)->delete();
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PELUNASAN_PEMBELIAN, $idPayment);
    }

    public function postingJurnal($idPayment)
    {
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $find = $this->findOne($idPayment, array(), ['payment_method','payment_method.coa','invoice','vendor']);
        if(!empty($find)){
            $coaUtangUsaha = SettingRepo::getOptionValue(SettingEnum::COA_UTANG_USAHA);
            $coaKasBank = SettingRepo::getOptionValue(SettingEnum::COA_KAS_BANK);
            if(!empty($find->payment_method)){
                $coaKasBank = $find->payment_method->coa_id;
            }

            $totalUtangUsaha = PurchasePaymentInvoice::where([['payment_id','=',$idPayment],['invoice_id','!=','0']])->sum('total_payment');
            $totalRetur = PurchasePaymentInvoice::where([['payment_id','=',$idPayment],['retur_id','!=','0']])->sum('total_payment');
            $totalDiskon = PurchasePaymentInvoice::where([['payment_id', '=', $idPayment], ['coa_id_discount', '!=', '']])->sum('total_discount');
            $totalLebih = PurchasePaymentInvoice::where([['payment_id', '=', $idPayment], ['coa_id_overpayment', '!=', '']])->sum('total_overpayment');
            $totalUtangUsaha = (($totalUtangUsaha-$totalRetur) + $totalDiskon) - $totalLebih;
            $arrJurnalDebet = array(
                'transaction_date' => $find->payment_date,
                'transaction_datetime' => $find->payment_date." ".date('H:i:s'),
                'created_by' => $find->created_by,
                'updated_by' => $find->created_by,
                'transaction_code' => TransactionsCode::PELUNASAN_PEMBELIAN,
                'coa_id' => $coaUtangUsaha,
                'transaction_id' => $find->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $find->payment_no,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => $totalUtangUsaha,
                'kredit' => 0,
                'note' => !empty($find->note) ? $find->note : 'Pelunasan Pembelian dengan nama supplier '.$find->vendor->vendor_name,
            );
            $jurnalTransaksiRepo->create($arrJurnalDebet);

            $findInvoice = $find->invoice;
            if(!empty($findInvoice)){
                foreach ($findInvoice as $val){
                    if(!empty($val->coa_id_discount)){
                        $decCoaDiscount = json_decode($val->coa_id_discount);
                        if(count($decCoaDiscount) > 0){
                            foreach ($decCoaDiscount as $item){
                                $arrJurnalKredit = array(
                                    'transaction_date' => $find->payment_date,
                                    'transaction_datetime' => $find->payment_date." ".date('H:i:s'),
                                    'created_by' => $find->created_by,
                                    'updated_by' => $find->created_by,
                                    'transaction_code' => TransactionsCode::PELUNASAN_PEMBELIAN,
                                    'coa_id' => $item->coa_id,
                                    'transaction_id' => $find->id,
                                    'transaction_sub_id' => 0,
                                    'created_at' => date("Y-m-d H:i:s"),
                                    'updated_at' => date("Y-m-d H:i:s"),
                                    'transaction_no' => $find->payment_no,
                                    'transaction_status' => JurnalStatusEnum::OK,
                                    'debet' => 0,
                                    'kredit' => Utility::remove_commas($item->nominal),
                                    'note' => !empty($find->note) ? $find->note : 'Pelunasan Pembelian dengan nama supplier '.$find->vendor->vendor_name,
                                );
                                $jurnalTransaksiRepo->create($arrJurnalKredit);
                            }
                        }
                    }
                    if(!empty($val->coa_id_overpayment)){
                        $decCoaLebih = json_decode($val->coa_id_overpayment);
                        if(count($decCoaLebih) > 0){
                            foreach ($decCoaLebih as $item){
                                $arrJurnalDebet = array(
                                    'transaction_date' => $find->payment_date,
                                    'transaction_datetime' => $find->payment_date." ".date('H:i:s'),
                                    'created_by' => $find->created_by,
                                    'updated_by' => $find->created_by,
                                    'transaction_code' => TransactionsCode::PELUNASAN_PEMBELIAN,
                                    'coa_id' => $item->coa_id,
                                    'transaction_id' => $find->id,
                                    'transaction_sub_id' => 0,
                                    'created_at' => date("Y-m-d H:i:s"),
                                    'updated_at' => date("Y-m-d H:i:s"),
                                    'transaction_no' => $find->payment_no,
                                    'transaction_status' => JurnalStatusEnum::OK,
                                    'debet' => Utility::remove_commas($item->nominal),
                                    'kredit' => 0,
                                    'note' => !empty($find->note) ? $find->note : 'Pelunasan Pembelian dengan nama supplier '.$find->vendor->vendor_name,
                                );
                                $jurnalTransaksiRepo->create($arrJurnalDebet);
                            }
                        }
                    }
                }
            }
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
                'note' => !empty($find->note) ? $find->note : 'Pelunasan Pembelian dengan nama supplier '.$find->vendor->vendor_name,
            );
            $jurnalTransaksiRepo->create($arrJurnalKredit);

        }
    }
}
