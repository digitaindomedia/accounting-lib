<?php

namespace Icso\Accounting\Http\Controllers\Persediaan;

use Icso\Accounting\Http\Requests\CreateMutationRequest;
use Icso\Accounting\Repositories\Persediaan\Mutation\MutationRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller;

class MutationController extends Controller
{
    protected $mutationRepo;

    public function __construct(MutationRepo $mutationRepo)
    {
        $this->mutationRepo = $mutationRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $warehouseId = $request->warehouse_id;
        $mutationType = $request->mutation_type;

        $where=array();
        if(!empty($warehouseId)){
            $where[] = array(
                'method' => 'where',
                'value' => [['warehouse_id','=',$warehouseId]]);
        }
        if (!empty($fromDate) && !empty($untilDate)) {
            $where[] = array(
                'method' => 'whereBetween',
                'value' => array('field' => 'adjustment_date', 'value' => [$fromDate,$untilDate]));
        }
        if(!empty($mutationType)){
            $where[] = array(
                'method' => 'where',
                'value' => [['mutation_type','=',$mutationType]]);
        }
        return compact('search', 'page', 'perpage', 'where');
    }

    public function getAllData(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->mutationRepo->getAllDataBy($search,$page,$perpage,$where);
        $total = $this->mutationRepo->getAllTotalDataBy($search,$where);
        $hasMore = Helpers::hasMoreData($total,$page,$data);
        if(count($data) > 0) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['has_more'] = $hasMore;
            $this->data['total'] = $total;
        }else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
            $this->data['data'] = array();
        }
        return response()->json($this->data);
    }

    public function store(CreateMutationRequest $request): \Illuminate\Http\JsonResponse
    {
        $res = $this->mutationRepo->store($request);
        if($res){
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';
            $this->data['data'] = '';
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal disimpan";
            $this->data['data'] = '';
        }
        return response()->json($this->data);
    }

    public function show(Request $request): \Illuminate\Http\JsonResponse
    {
        $res = $this->mutationRepo->findOne($request->id,array(),['fromwarehouse','towarehouse','mutationproduct','mutationproduct.product','mutationproduct.unit']);
        if($res){
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal ditemukan";
            $this->data['data'] = '';
        }
        return response()->json($this->data);
    }

    public function destroy(Request $request)
    {
        $id = $request->id;
        DB::beginTransaction();
        try
        {
            $this->mutationRepo->deleteAdditional($id);
            $this->mutationRepo->delete($id);
            DB::commit();
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil dihapus';
            $this->data['data'] = array();
        }
        catch (\Exception $e) {
            DB::rollback();
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
            $this->data['data'] = array();
        }
        return response()->json($this->data);
    }

    public function deleteAll(Request $request)
    {
        $reqData = json_decode(json_encode($request->ids));
        $successDelete = 0;
        $failedDelete = 0;
        if(count($reqData) > 0){
            foreach ($reqData as $id){
                DB::beginTransaction();
                try
                {
                    $this->mutationRepo->deleteAdditional($id);
                    $this->mutationRepo->delete($id);
                    DB::commit();
                    $successDelete = $successDelete + 1;
                }
                catch (\Exception $e) {
                    DB::rollback();
                    $failedDelete = $failedDelete + 1;
                }
            }
        }

        if($successDelete > 0) {
            $this->data['status'] = true;
            $this->data['message'] = "$successDelete Data berhasil dihapus <br /> $failedDelete Data tidak bisa dihapus";
            $this->data['data'] = array();
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
            $this->data['data'] = array();
        }
        return response()->json($this->data);
    }
}
