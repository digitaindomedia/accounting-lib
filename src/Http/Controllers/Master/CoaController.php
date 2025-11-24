<?php
namespace Icso\Accounting\Http\Controllers\Master;


use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\CoaExport;
use Icso\Accounting\Http\Requests\CreateCoaRequest;
use Icso\Accounting\Models\Master\Coa;
use Icso\Accounting\Repositories\Master\Coa\CoaRepo;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class CoaController extends Controller
{
    protected $coaRepo;

    public function __construct(CoaRepo $coaRepo)
    {
        $this->coaRepo = $coaRepo;
    }

    public function store(CreateCoaRequest $request)
    {
        $res = $this->coaRepo->store($request);
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

    public function update(CreateCoaRequest $request) {

        $res = $this->coaRepo->updateDate($request);
        if($res)
        {
            $this->data['status'] = true;
            $this->data['data'] = $res;
            $this->data['message'] = 'Data berhasil disimpan';

        }else{
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal disimpan';
        }
        return response()->json($this->data);
    }

    public function getAllData(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $level = $request->level;
        $category = $request->category;
        try
        {
            $where = array();
            if(!empty($level)){
                $where[] = ['coa_level', '=', $level];
            }
            if(!empty($category)){
                $where[] = ['coa_category', '=', $category];
            }
            $data = $this->coaRepo->getAllDataBy($search,$page,$perpage,$where);
            $total = $this->coaRepo->getAllTotalDataBy($search,$where);
            $has_more = false;
            $page = $page + count($data);
            if($total > $page)
            {
                $has_more = true;
            }
            if($data)
            {
                if(count($data) > 0)
                {
                    foreach ($data as $item)
                    {
                        $posisi = '0';
                        $root_parent = $this->coaRepo->getParentRoot($item->id);
                        if(!empty($root_parent))
                        {
                            $posisi = $root_parent->coa_position;
                        }
                        $item->coa_position = $posisi;
                    }
                }
                $this->data['status'] = true;
                $this->data['message'] = 'Data berhasil ditemukan baru';
                $this->data['data'] = $data;
                $this->data['has_more'] = $has_more;
                $this->data['total'] = $total;

            }else{
                $this->data['status'] = false;
                $this->data['message'] = 'Data tidak ditemukan';
                $this->data['data'] = array();
                $this->data['has_more'] = $has_more;
            }
        } catch (\Exception $e) {
            $this->data['status'] = false;
            $this->data['message'] = 'parameter salah';
        }
        return response()->json($this->data);
    }

    public function getAllLevelData(Request $request)
    {
        $level = $request->level;
        $parent = $request->parent;

        $res = $this->coaRepo->findAllByWhere(array('coa_level' => $level, 'coa_parent' => $parent), array());
        if($res)
        {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        }
        else{
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    public function getAllCategoryData(Request $request)
    {
        $coa_category = $request->coa_category;
        $res = $this->coaRepo->findAllByWhere(array('coa_level' => '4', 'coa_category' => $coa_category), array());
        if($res)
        {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        }
        else{
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    public function destroy(Request $request)
    {
        $id = $request->id;
        try
        {
            $data = Coa::find($id);
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
                $this->data['message'] = 'Data gagal dihapus';
            }
        } catch (\Exception $e) {
            $this->data['status'] = false;
            $this->data['message'] = 'parameter salah';
        }
        return response()->json($this->data);
    }

    public function show(Request $request)
    {
        $id = $request->id;
        $data = $this->coaRepo->findOne($id);
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

    public function getAllMasterCoa()
    {
        $res = $this->coaRepo->findAllByWhere(array(),['coa_code', 'asc']);
        if($res)
        {
            $arr = array();
            foreach ($res as $r)
            {
                $arr[] = $r;
            }
            $res_child = array();
            foreach ($arr as $a)
            {
                $res_child[$a['coa_parent']][] = $a;
            }
            $res_chi = '';
            if(count($res_child) > 0)
            {
                $res_chi = $this->coaRepo->getChildRoot($res_child,$res_child[0]);
            }

            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res_chi;
        }
        else{
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    public function getCoaCount(): \Illuminate\Http\JsonResponse
    {
        $res = Coa::where('coa_level','4')->count();
        $response = [
            'status' => true,
            'message' => 'Data berhasil ditemukan',
            'data' => $res
        ];

        return response()->json($response);
    }

    public function exportExcel()
    {
       return Excel::download(new CoaExport, 'coa.xlsx');
    }

    public function exportCsv()
    {
        return Excel::download(new CoaExport, 'coa.xlsx', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportPdf()
    {
        $data = Coa::where('coa_level', 1)
            ->with('children.children.children')
            ->get();

        $result = [];

        // Rekursif flatten
        $flatten = function ($coa, &$result) use (&$flatten) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', max(0, $coa->coa_level - 1));
            $label = trim(($coa->coa_code ? $coa->coa_code . ' ' : '') . $coa->coa_name);
            $result[] = $indent . $label;

            if ($coa->children && count($coa->children) > 0) {
                foreach ($coa->children as $child) {
                    $flatten($child, $result);
                }
            }
        };

        foreach ($data as $coa) {
            $flatten($coa, $result);
        }

        $pdf = Pdf::loadView('master.coa', ['coaList' => $result]);
        return $pdf->download('coa.pdf');
    }


}
