<?php

namespace Icso\Accounting\Http\Controllers\Akuntansi;

use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Repositories\Akuntansi\BukuPembantuRepo;
use Icso\Accounting\Repositories\Akuntansi\PelunasanBukuPembantuRepo;
use Icso\Accounting\Utils\InputType;
use Icso\Accounting\Utils\Utility;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BukuPembantuController extends Controller
{
    protected $bukuPembantuRepo;
    protected $pelunasanBukuPembantuRepo;

    public function __construct(BukuPembantuRepo $bukuPembantuRepo, PelunasanBukuPembantuRepo $pelunasanBukuPembantuRepo)
    {
        $this->bukuPembantuRepo = $bukuPembantuRepo;
        $this->pelunasanBukuPembantuRepo = $pelunasanBukuPembantuRepo;
    }

    public function getAllData(Request $request){
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $inputType = $request->input_type;
        $coaId = $request->coa_id;
        $fieldName = $request->field_name;

        $where=array();
        if(!empty($inputType)){
            $where[] = ['input_type','=',$inputType];
        }
        if(!empty($coaId)){
            $where[] = ['coa_id','=',$coaId];
        }
        if(!empty($fieldName)){
            $where[] = ['field_name','=',$fieldName];
        }
        $data = $this->bukuPembantuRepo->getAllDataBy($search, $page, $perpage,$where);
        $total = $this->bukuPembantuRepo->getAllTotalDataBy($search, $where);
        $has_more = false;
        $page = $page + count($data);
        if($total > $page)
        {
            $has_more = true;
        }
        $findCoa = Coa::where(array('id' => $coaId))->first();
        if(count($data) > 0) {
            foreach ($data as $item) {
                $paid = $this->pelunasanBukuPembantuRepo->getAllPaymentByBukuPembantuId($item->id);
                $left_bill = $item->nominal - $paid;
                $item->left_bill = $left_bill;
            }
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['coa'] = $findCoa;
            $this->data['has_more'] = $has_more;
            $this->data['total'] = $total;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
            $this->data['data'] = array();
            $this->data['coa'] = $findCoa;
            $this->data['has_more'] = $has_more;
            $this->data['total'] = $total;
        }
        return response()->json($this->data);
    }

    public function storeSaldoAwal(Request $request){
        $coaId = $request->coa_id;
        $userId = $request->user_id;
        $bukuPembantu = json_decode(json_encode($request->buku_pembantu));
        DB::beginTransaction();
        try {
            if (count($bukuPembantu) > 0) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                foreach ($bukuPembantu as $i => $item) {
                    $req = new Request();
                    $req->coa_id = $coaId;
                    $req->user_id = $userId;
                    $req->ref_date = date("Y-m-d", strtotime($item->ref_date));
                    $req->ref_no = $item->ref_no;
                    $req->note = $item->note;
                    $req->field_name = $item->field_name;
                    $req->nominal = Utility::remove_commas($item->nominal);
                    $req->input_type = InputType::SALDO_AWAL;
                    $this->bukuPembantuRepo->store($req);
                }
            }
            DB::commit();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';
            $this->data['data'] = '';
        }catch (\Exception $e) {
            DB::rollBack();
            $this->data['status'] = false;
            $this->data['message'] = $e->getMessage();
        }
        return response()->json($this->data);
    }

    public function updateSaldoAwal(Request $request)
    {
        $res = $this->bukuPembantuRepo->updateData($request);
        if($res){
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';
            $this->data['data'] = "";
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal disimpan";
        }
        return response()->json($this->data);
    }

}
