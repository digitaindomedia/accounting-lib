<?php
namespace Icso\Accounting\Http\Controllers\Master;


use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\UnitExport;
use Icso\Accounting\Http\Requests\CreateUnitRequest;
use Icso\Accounting\Models\Master\ProductConvertion;
use Icso\Accounting\Models\Master\Unit;
use Icso\Accounting\Repositories\Master\Product\ProductRepo;
use Icso\Accounting\Repositories\Master\UnitRepo;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class UnitController extends Controller
{
    protected $unitRepo;
    protected $productRepo;

    public function __construct(UnitRepo $unitRepo, ProductRepo $productRepo)
    {
        $this->unitRepo = $unitRepo;
        $this->productRepo = $productRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $userId = $request->user_id;
        $productId = $request->product_id;
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        return compact('search','userId', 'productId', 'page', 'perpage');
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        if(empty($productId)){
            $data = $this->unitRepo->getAllDataBy($search,$page,$perpage);
            $total = $this->unitRepo->getAllTotalDataBy($search);
            $has_more = false;
            $page = $page + count($data);
            if($total > $page)
            {
                $has_more = true;
            }
        } else {
            $findKonversi = ProductConvertion::where(array('product_id' => $productId))->with(['unit'])->get();
            $data = array();
            $findProduk = $this->productRepo->findOne($productId,array(),['unit']);
            if(!empty($findProduk)){
                $data[] = $findProduk->unit;
            }
            if(count($findKonversi) > 0) {
                foreach ($findKonversi as $konv) {
                    $data[] = $konv->unit;
                }
            }
            $total = count($data);
            $has_more = false;
            $page = $page + count($data);
            if($total > $page)
            {
                $has_more = true;
            }
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

    public function store(CreateUnitRequest $request)
    {
        $res = $this->unitRepo->store($request);
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
        $data = $this->unitRepo->findOne($id);
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
            $data = Unit::find($id);
            if($data)
            {
                if($data->canDelete()){
                    $data->delete();
                    $this->data['status'] = true;
                    $this->data['message'] = 'Data berhasil dihapus ';
                } else {
                    $this->data['status'] = false;
                    $this->data['message'] = 'Data tidak bisa dihapus ';
                }


            }else{
                $this->data['status'] = false;
                $this->data['message'] = 'Data tidak ditemukan';
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
                $data = Unit::find($id);
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

    public function export(Request $request)
    {
        return $this->exportAsFormat($request, 'satuan.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request, 'satuan.csv');
    }

    public function exportPdf(Request $request)
    {
        $data = $this->getUnitData($request);

        $pdf = Pdf::loadView('accounting::master.unit', ['arrData' => $data]);
        return $pdf->download('satuan.pdf');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->getUnitData($request);
        return Excel::download(new UnitExport($data), $filename);
    }

    private function getUnitData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params); // $search, $page, $categoryType

        return $this->unitRepo->getAllDataBy($search, $page, 100);
    }

}
