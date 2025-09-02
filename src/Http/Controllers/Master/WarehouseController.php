<?php
namespace Icso\Accounting\Http\Controllers\Master;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\WarehouseExport;
use Icso\Accounting\Http\Requests\CreateWarehouseRequest;
use Icso\Accounting\Models\Master\Warehouse;
use Icso\Accounting\Repositories\Master\WarehouseRepo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class WarehouseController extends Controller
{
    protected $warehouseRepo;

    public function __construct(WarehouseRepo $warehouseRepo)
    {
        $this->warehouseRepo = $warehouseRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        return compact('search', 'page', 'perpage');
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->warehouseRepo->getAllDataBy($search,$page,$perpage);
        $total = $this->warehouseRepo->getAllTotalDataBy($search);
        $has_more = false;
        $page = $page + count($data);
        if($total > $page)
        {
            $has_more = true;
        }
        if($data)
        {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['has_more'] = $has_more;
            $this->data['total'] = $total;
        } else{
            $this->data['status'] = false;
            $this->data['has_more'] = $has_more;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    public function store(CreateWarehouseRequest $request)
    {
        $res = $this->warehouseRepo->store($request);
        if($res)
        {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';

        }else{
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal disimpan';
        }
        return response()->json($this->data);
    }

    public function show(Request $request)
    {
        $id = $request->id;
        $data = $this->warehouseRepo->findOne($id);
        if($data)
        {
            $this->data['status'] = true;
            $this->data['message'] = 'data berhasil ditemukan';
            $this->data['data'] = $data;

        }else{
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal ditemukan';
        }
        return response()->json($this->data);
    }

    public function destroy(Request $request)
    {
        $id = $request->id;
        try
        {
            $data = Warehouse::find($id);
            if($data)
            {
                if($data->canDelete()){
                    $data->delete();
                    $this->data['status'] = true;
                    $this->data['message'] = 'Data berhasil dihapus ';
                } else {
                    $this->data['status'] = false;
                    $this->data['message'] = 'Data tidak bisa dihapus';
                }

            }else{
                $this->data['status'] = false;
                $this->data['message'] = 'Data gagal dihapus';
            }
        } catch (\Exception $e) {
            $this->data['status'] = false;
            $this->data['message'] = 'Terjadi kesalahan dalam hapus data';
        }
        return response()->json($this->data);
    }

    public function deleteAll(Request $request)
    {
        $reqData = $request->ids;
        $successDelete = 0;
        $failedDelete = 0;
        if(count($reqData) > 0){
            foreach ($reqData as $res){
                $id = $res['id'];
                $data = Warehouse::find($id);
                if($data)
                {
                    if($data->canDelete()) {
                        $data->delete();
                        $successDelete = $successDelete + 1;
                    }
                    else {
                        $failedDelete = $failedDelete + 1;
                    }
                } else {
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

    public function exportExcel(Request $request)
    {
        return $this->exportAsFormat($request, 'gudang.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request, 'gudang.csv');
    }

    public function exportPdf(Request $request)
    {
        $data = $this->getWarehouseData($request);

        $pdf = Pdf::loadView('master.gudang', ['arrData' => $data]);
        return $pdf->download('gudang.pdf');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->getWarehouseData($request);
        return Excel::download(new WarehouseExport($data), $filename);
    }

    private function getWarehouseData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params); // $search, $page, $categoryType

        return $this->warehouseRepo->getAllDataBy($search, $page, 100);
    }

}
