<?php

namespace Icso\Accounting\Repositories\AsetTetap\Penjualan;

use Icso\Accounting\Enums\InvoiceStatusEnum;
use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Enums\SettingEnum;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\AsetTetap\Pembelian\PurchasePayment;
use Icso\Accounting\Models\AsetTetap\Penjualan\SalesInvoice;
use Icso\Accounting\Models\AsetTetap\Penjualan\SalesInvoiceMeta;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\AsetTetap\Pembelian\OrderRepo;
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

class SalesInvoiceRepo extends ElequentRepository
{
    protected $model;

    public function __construct(SalesInvoice $model)
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
            $query->where('sales_no', 'like', '%' .$search. '%');
            $query->orWhereHas('asettetap', function ($query) use ($search) {
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
        })->with(['profitlosscoa','asettetap','asettetap.aset_tetap_coa', 'asettetap.dari_akun_coa','asettetap.akumulasi_penyusutan_coa','asettetap.penyusutan_coa'])->orderBy('sales_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = []): int
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new $this->model;
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('sales_no', 'like', '%' .$search. '%');
            $query->orWhereHas('asettetap', function ($query) use ($search) {
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
        $salesNo = $request->sales_no;
        if(empty($receivedNo)){
            $salesNo = self::generateCodeTransaction(new SalesInvoice(),KeyNomor::NO_SALES_INVOICE_ASET_TETAP,'sales_no','sales_date');
        }
        $salesDate = Utility::changeDateFormat($request->sales_date);
        $note = !empty($request->note) ? $request->note : '';
        $userId = $request->user_id;
        $price = !empty($request->price) ? Utility::remove_commas($request->price) : '0';
        $profitLoss = !empty($request->profit_loss) ? Utility::remove_commas($request->profit_loss) : '0';
        $profitLossCoaId = $request->profit_loss_coa_id;
        $asetTetapId = $request->aset_tetap_id;
        $nilaiPenyusutan = !empty($request->nilai_penyusutan) ? Utility::remove_commas($request->nilai_penyusutan) : '0';
        $buyerName = !empty($request->buyer_name) ? $request->buyer_name : '';

        $arrData = array(
            'sales_date' => $salesDate,
            'sales_no' => $salesNo,
            'price' => $price,
            'profit_loss' => $profitLoss,
            'note' => $note,
            'profit_loss_coa_id' => $profitLossCoaId,
            'aset_tetap_id' => $asetTetapId,
            'nilai_penyusutan' => $nilaiPenyusutan,
            'buyer_name' => $buyerName,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s')
        );
        DB::beginTransaction();
        try {
            if (empty($id)) {
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $arrData['created_by'] = $userId;
                $arrData['reason'] = '';
                $arrData['sales_status'] = InvoiceStatusEnum::BELUM_LUNAS;
                $res = $this->create($arrData);
            } else {
                $res = $this->update($arrData, $id);
            }
            if ($res) {
                if (!empty($id)) {
                    $idInvoice = $id;
                    $this->deleteAdditional($idInvoice);
                } else {
                    $idInvoice = $res->id;
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
                                    'sales_id' => $idInvoice,
                                    'meta_key' => 'upload',
                                    'meta_value' => $resUpload
                                );
                                SalesInvoiceMeta::create($arrUpload);
                            }
                        }
                    }
                }
                $this->postingJurnal($idInvoice);
                OrderRepo::changeStatus($asetTetapId,StatusEnum::TERJUAL);
                DB::commit();
                return true;
            } else {
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

    public function delete($id)
    {
        DB::beginTransaction();
        try {
            $this->deleteAdditional($id);
            $this->deleteByWhere(array('id' => $id));
            DB::commit();
            return true;
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
        JurnalTransaksiRepo::deleteJurnalTransaksi(TransactionsCode::PENJUALAN_ASET_TETAP, $id);
        SalesInvoiceMeta::where(array('sales_id' => $id))->delete();
    }

    public static function changeStatus($id,$status= StatusEnum::LUNAS)
    {
        $res = SalesInvoice::findOrFail($id);
        $res->sales_status = $status;
        $res->save();
    }

    public function postingJurnal($idSales)
    {
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $coaPiutangLainLain = SettingRepo::getOptionValue(SettingEnum::COA_PIUTANG_LAIN_LAIN);
        $find = $this->findOne($idSales,array(),['profitlosscoa','asettetap','asettetap.aset_tetap_coa', 'asettetap.dari_akun_coa','asettetap.akumulasi_penyusutan_coa','asettetap.penyusutan_coa']);
        if(!empty($find)) {
            $invDate = $find->sales_date;
            $invNo = $find->sales_no;

            //jurnal debet
            $arrJurnalDebet = array(
                'transaction_date' => $invDate,
                'transaction_datetime' => $invDate." ".date('H:i:s'),
                'created_by' => $find->created_by,
                'updated_by' => $find->created_by,
                'transaction_code' => TransactionsCode::PENJUALAN_ASET_TETAP,
                'coa_id' => $coaPiutangLainLain,
                'transaction_id' => $find->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $invNo,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => $find->price,
                'kredit' => 0,
                'note' => !empty($find->note) ? $find->note : 'Penjualan Aset Tetap berupa '.$find->asettetap->nama_aset,
            );
            $jurnalTransaksiRepo->create($arrJurnalDebet);
            if($find->asettetap->status_penyusutan == '0'){
                if(!empty($find->asettetap->akumulasi_penyusutan_coa_id)){
                    $arrJurnalDebet = array(
                        'transaction_date' => $invDate,
                        'transaction_datetime' => $invDate." ".date('H:i:s'),
                        'created_by' => $find->created_by,
                        'updated_by' => $find->created_by,
                        'transaction_code' => TransactionsCode::PENJUALAN_ASET_TETAP,
                        'coa_id' => $find->asettetap->akumulasi_penyusutan_coa_id,
                        'transaction_id' => $find->id,
                        'transaction_sub_id' => 0,
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                        'transaction_no' => $invNo,
                        'transaction_status' => JurnalStatusEnum::OK,
                        'debet' => $find->nilai_penyusutan,
                        'kredit' => 0,
                        'note' => !empty($find->note) ? $find->note : 'Penjualan Aset Tetap berupa '.$find->asettetap->nama_aset,
                    );
                    $jurnalTransaksiRepo->create($arrJurnalDebet);
                }

            }
            if($find->profit_loss > 0){
                $arrJurnalDebet = array(
                    'transaction_date' => $invDate,
                    'transaction_datetime' => $invDate." ".date('H:i:s'),
                    'created_by' => $find->created_by,
                    'updated_by' => $find->created_by,
                    'transaction_code' => TransactionsCode::PENJUALAN_ASET_TETAP,
                    'coa_id' => $find->profit_loss_coa_id,
                    'transaction_id' => $find->id,
                    'transaction_sub_id' => 0,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_no' => $invNo,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'debet' => $find->profit_loss,
                    'kredit' => 0,
                    'note' => !empty($find->note) ? $find->note : 'Penjualan Aset Tetap berupa '.$find->asettetap->nama_aset,
                );
                $jurnalTransaksiRepo->create($arrJurnalDebet);
            }

            //Jurnal kredit
            $arrJurnalKredit = array(
                'transaction_date' => $invDate,
                'transaction_datetime' => $invDate." ".date('H:i:s'),
                'created_by' => $find->created_by,
                'updated_by' => $find->created_by,
                'transaction_code' => TransactionsCode::PENJUALAN_ASET_TETAP,
                'coa_id' => $find->asettetap->aset_tetap_coa_id,
                'transaction_id' => $find->id,
                'transaction_sub_id' => 0,
                'created_at' => date("Y-m-d H:i:s"),
                'updated_at' => date("Y-m-d H:i:s"),
                'transaction_no' => $invNo,
                'transaction_status' => JurnalStatusEnum::OK,
                'debet' => 0,
                'kredit' => $find->asettetap->harga_beli,
                'note' => !empty($find->note) ? $find->note : 'Penjualan Aset Tetap berupa '.$find->asettetap->nama_aset,
            );
            $jurnalTransaksiRepo->create($arrJurnalKredit);
            if($find->profit_loss < 0){
                $arrJurnalDebet = array(
                    'transaction_date' => $invDate,
                    'transaction_datetime' => $invDate." ".date('H:i:s'),
                    'created_by' => $find->created_by,
                    'updated_by' => $find->created_by,
                    'transaction_code' => TransactionsCode::PENJUALAN_ASET_TETAP,
                    'coa_id' => $find->profit_loss_coa_id,
                    'transaction_id' => $find->id,
                    'transaction_sub_id' => 0,
                    'created_at' => date("Y-m-d H:i:s"),
                    'updated_at' => date("Y-m-d H:i:s"),
                    'transaction_no' => $invNo,
                    'transaction_status' => JurnalStatusEnum::OK,
                    'debet' => 0,
                    'kredit' => abs($find->profit_loss),
                    'note' => !empty($find->note) ? $find->note : 'Penjualan Aset Tetap berupa '.$find->asettetap->nama_aset,
                );
                $jurnalTransaksiRepo->create($arrJurnalDebet);
            }
        }
    }

    public static function getTotalPayment($idInvoice, $idPayment='')
    {
        $res = PurchasePayment::where([['invoice_id', $idInvoice], ['payment_type' ,InputType::SALES]]);
        if(!empty($idPayment)){
            $res->where('id', '!=', $idPayment);
        }
        $total =  $res->sum('total');
        return $total;
    }
}
