<?php

namespace Icso\Accounting\Repositories\Akuntansi;

use Icso\Accounting\Enums\JurnalStatusEnum;
use Icso\Accounting\Models\Akuntansi\BukuPembantu;
use Icso\Accounting\Repositories\ElequentRepository;
use Icso\Accounting\Utils\InputType;
use Icso\Accounting\Utils\Utility;
use Illuminate\Http\Request;

class BukuPembantuRepo extends ElequentRepository
{

    protected $model;

    public function __construct(BukuPembantu $model)
    {
        parent::__construct($model);
        $this->model = $model;
    }
    public function getAllDataBy($search, $page, $perpage, array $where = [])
    {
        // TODO: Implement getAllDataBy() method.
        $model = new BukuPembantu();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('ref_date','desc')->offset($page)->limit($perpage)->get();
        return $dataSet;
    }

    public function getAllTotalDataBy($search, array $where = [])
    {
        // TODO: Implement getAllTotalDataBy() method.
        $model = new BukuPembantu();
        $dataSet = $model->when(!empty($search), function ($query) use($search){
            $query->where('ref_no', 'like', '%' .$search. '%');
        })->when(!empty($where), function ($query) use($where){
            $query->where($where);
        })->orderBy('ref_date','desc')->count();
        return $dataSet;
    }

    public function store(Request $request, array $other = [])
    {
        // TODO: Implement store() method.
       $fieldName = $request->field_name;
       $bukuPembantuDate = $request->ref_date;
       $jurnalId = !empty($request->jurnal_id) ? $request->jurnal_id : '0';
       $jurnalAkunId = !empty($request->jurnal_akun_id) ? $request->jurnal_akun_id : '0';
       $note = !empty($request->note) ? $request->note : '';
       $noRef = $request->ref_no;
       $userId = $request->user_id;
       $nominal = !empty($request->nominal) ? Utility::remove_commas($request->nominal) : '0';
       $coaId = !empty($request->coa_id) ? $request->coa_id : '0';
       $inputType = !empty($request->input_type) ? $request->input_type : '';
       $status = !empty($request->status) ? $request->status : JurnalStatusEnum::BELUM_LUNAS;
       $arrData = array(
            'field_name' => $fieldName,
            'ref_no' => $noRef,
            'ref_date' => $bukuPembantuDate,
            'note' => $note,
            'status_ref' => $status,
            'jurnal_id' => $jurnalId,
            'created_by' => $userId,
            'updated_by' => $userId,
            'coa_id' => $coaId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'jurnal_akun_id' => $jurnalAkunId,
            'nominal' => $nominal,
            'input_type' => $inputType,
       );
       $res = $this->create($arrData);
       return $res;
    }

    public function updateData(Request $request)
    {
        $fieldName = $request->field_name;
        $bukuPembantuDate = $request->ref_date;
        $note = !empty($request->note) ? $request->note : '';
        $noRef = $request->ref_no;
        $userId = $request->user_id;
        $nominal = !empty($request->nominal) ? Utility::remove_commas($request->nominal) : '0';

        $arrData = array(
            'field_name' => $fieldName,
            'ref_no' => $noRef,
            'ref_date' => $bukuPembantuDate,
            'note' => $note,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
            'nominal' => $nominal,
        );
        $res = $this->update($arrData, $request->id);
        return $res;
    }

    public static function getTotalInvoiceBySaldoAwalCoaId($coaId)
    {
        $getTotal = BukuPembantu::where(array('coa_id' => $coaId, 'input_type' => InputType::SALDO_AWAL))->sum('nominal');
        return $getTotal;
    }
}
