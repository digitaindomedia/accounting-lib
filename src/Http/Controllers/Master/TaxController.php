<?php
namespace Icso\Accounting\Http\Controllers\Master;


use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\TaxExport;
use Icso\Accounting\Http\Requests\CreateTaxRequest;
use Icso\Accounting\Models\Master\Tax;
use Icso\Accounting\Repositories\Master\TaxRepo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class TaxController extends Controller
{
    protected $taxRepo;

    public function __construct(TaxRepo $taxRepo)
    {
        $this->taxRepo = $taxRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $userId = $request->user_id;
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $taxType = $request->type;
        return compact('search', 'userId', 'page', 'perpage','taxType');;
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $where = array();
        if(!empty($taxType)){
            $where[] = ['tax_type', '=', $taxType];
        }
        $data = $this->taxRepo->getAllDataBy($search,$page,$perpage,$where);
        $total = $this->taxRepo->getAllTotalDataBy($search,$where);
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

    public function store(CreateTaxRequest $request)
    {
        $res = $this->taxRepo->store($request);
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
        $data = $this->taxRepo->findOne($id,array(),['taxgroup','taxgroup.tax','purchasecoa','salescoa']);
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
            $data = Tax::find($id);
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
                $data = Tax::find($id);
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
        return $this->exportAsFormat($request, 'pajak.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request, 'pajak.csv');
    }

    public function exportPdf(Request $request)
    {
        $data = $this->getTaxData($request);
        $export = new TaxExport($data);
        $pdf = PDF::loadView('master/tax', ['arrData' => $export->collection()]);
        return $pdf->download('pajak.pdf');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->getTaxData($request);
        return Excel::download(new TaxExport($data), $filename);
    }

    private function getTaxData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params); // $search, $page, $categoryType

        return $this->taxRepo->getAllDataBy($search, $page, 100);
    }
}
