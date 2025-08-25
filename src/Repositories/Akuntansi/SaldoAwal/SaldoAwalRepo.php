<?php
namespace Icso\Accounting\Repositories\Akuntansi\SaldoAwal;

use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Models\Akuntansi\JurnalTransaksi;
use Icso\Accounting\Models\Akuntansi\SaldoAwal;
use Icso\Accounting\Models\Akuntansi\SaldoAwalAkun;
use Icso\Accounting\Repositories\Akuntansi\JurnalTransaksiRepo;
use Icso\Accounting\Repositories\Akuntansi\SaldoAwalAkunRepo;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Utils\Constants;
use Icso\Accounting\Utils\TransactionsCode;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaldoAwalRepo extends ElequentRepository
{

    protected $model;

    public function __construct(SaldoAwal $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }

    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new SaldoAwal();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('saldo_date','asc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new SaldoAwal();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('saldo_date','asc')->get();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
        $id = $request->id;
        $userId = $request->user_id;
        $saldoDate = $request->saldo_date;
        $saldoMonth = $request->saldo_month;
        $saldoYear = $request->saldo_year;
        DB::beginTransaction();
        try {
            $noRef = "SALDOAWAL-".date("His");
            $arrData = array(
                'saldo_date' => $saldoDate,
                'saldo_month' => $saldoMonth,
                'saldo_year' => $saldoYear,
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s'),
                'is_default' => Constants::AKTIF
            );
            if(empty($id))
            {
                $arrData['ref_no'] = $noRef;
                $arrData['created_by'] = $userId;
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $res = $this->create($arrData);
            }
            else {
                $res = $this->update($arrData, $id);
            }
            if($res){
                if(!empty($id)) {
                    $countSaldoAkun = SaldoAwalAkun::where(array('saldo_id' => $id))->count();
                    if($countSaldoAkun > 0 ){
                        JurnalTransaksi::where(array('transaction_code' => TransactionsCode::SALDO_AWAL))->update(array(
                            'transaction_date' => $saldoDate,
                            'transaction_datetime' => $saldoDate." ".date('H:i:s')
                        ));
                    }
                }
                DB::commit();
                return true;
            }
            else {
                DB::rollback();
                return false;
            }

        }
        catch (\Exception $e) {
            // Rollback Transaction
            //echo $e->getMessage();
            DB::rollback();
            return false;
        }
    }

    public function delete($id)
    {
        // TODO: Implement delete() method.
        DB::beginTransaction();
        try {
            $this->deleteAdditionalData($id);
            $res = $this->deleteByWhere(array('id' => $id));
            return true;
        }
        catch (\Exception $e) {
            // Rollback Transaction
            DB::rollback();
            return false;
        }

    }

    public function deleteAdditionalData($id)
    {
        // TODO: Implement deleteAdditionalData() method.
        $saldoAkunRepo = new SaldoAwalAkunRepo(new SaldoAwalAkun());
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $saldoAkunRepo->deleteByWhere(array('saldo_id' => $id));
        $jurnalTransaksiRepo->deleteByWhere(array('transaction_code' => TransactionsCode::SALDO_AWAL,'transaction_id' => $id));
    }

    public function storeSaldoAkun(Request $request)
    {
        // TODO: Implement storeSaldoAkun() method.
        $saldoAkunRepo = new SaldoAwalAkunRepo(new SaldoAwalAkun());
        $jurnalTransaksiRepo = new JurnalTransaksiRepo(new JurnalTransaksi());
        $coa = $request->saldo_akun;
      //  $debet = json_decode($request->debet);
       // $kredit = json_decode($request->kredit);
        $id = $request->id;
        $userId = $request->user_id;
        $saldoDate = $request->saldo_date;
        DB::beginTransaction();
        try {
            $noRef = "SALDOAWAL-".date("His");
            $arrData = array(
                'saldo_date' => $saldoDate,
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s'),
                'is_default' => Constants::AKTIF
            );
            if(empty($id))
            {
                $arrData['created_by'] = $userId;
                $arrData['created_at'] = date('Y-m-d H:i:s');
                $res = $this->create($arrData);
            }
            if(!empty($id)) {
                $this->deleteAdditionalData($id);
                $idData = $id;
            } else {
               // $idData = $res->id;
            }
            if(count($coa) > 0)
            {
                $i = 0;
                foreach ($coa as $item)
                {
                    $debet = (!empty($item['saldo_akun']['debet']) ? Utility::remove_commas($item['saldo_akun']['debet']) : '0');
                    $kredit = (!empty($item['saldo_akun']['kredit']) ? Utility::remove_commas($item['saldo_akun']['kredit']) : '0');
                    $arr_item = array(
                        'saldo_id' => $idData,
                        'coa_id' => $item['id'],
                        'debet' => $debet,
                        'kredit' => $kredit,
                    );
                    $resAkun = $saldoAkunRepo->create($arr_item);
                    $arr_jurnal = array(
                        'transaction_date' => $saldoDate,
                        'transaction_datetime' => $saldoDate." ".date('H:i:s'),
                        'created_by' => $userId,
                        'updated_by' => $userId,
                        'transaction_code' => TransactionsCode::SALDO_AWAL,
                        'coa_id' => $item['id'],
                        'transaction_id' => $idData,
                        'transaction_sub_id' => $resAkun->id,
                        'created_at' => date("Y-m-d H:i:s"),
                        'updated_at' => date("Y-m-d H:i:s"),
                        'transaction_no' => $noRef,
                        'transaction_status' => JurnalStatusEnum::OK,
                        'debet' => $debet,
                        'kredit' => $kredit,
                        'note' => "Saldo Awal",
                    );
                    $jurnalTransaksiRepo->create($arr_jurnal);
                    $i = $i + 1;
                }
            }
            DB::commit();
            return true;
        }
        catch (\Exception $e) {
            // Rollback Transaction
            Log::error($e->getMessage());
            DB::rollback();
            return false;
        }
    }
}
