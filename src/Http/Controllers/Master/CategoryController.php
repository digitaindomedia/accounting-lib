<?php
namespace Icso\Accounting\Http\Controllers\Master;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\CategoryExport;
use Icso\Accounting\Http\Requests\CreateCategoryRequest;
use Icso\Accounting\Models\Master\Category;
use Icso\Accounting\Repositories\Master\CategoryRepo;
use Icso\Accounting\Utils\RequestAuditHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class CategoryController extends Controller
{
    protected $categoryRepo;

    public function __construct(CategoryRepo $categoryRepo)
    {
        $this->categoryRepo = $categoryRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $userId = $request->user_id;
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $categoryType = $request->category_type;

        return compact('search', 'userId', 'page', 'perpage', 'categoryType');
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->categoryRepo->getAllDataBy($search,$page,$perpage,array('category_type' => $categoryType));
        $total = $this->categoryRepo->getAllTotalDataBy($search,array('category_type' => $categoryType));
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

    public function store(CreateCategoryRequest $request)
    {
        $res = $this->categoryRepo->store($request);
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
    public function show(Request $request)
    {
        $id = $request->id;
        $data = $this->categoryRepo->findOne($id);
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
            $data = Category::find($id);
            if($data)
            {
                if($data->canDelete()){
                    $oldData = $data;
                    $data->delete();
                    $this->activityLog->log([
                        'user_id'         => $request->user_id,
                        'action'          => 'Hapus data master kategori dengan nama '.$oldData->category_name,
                        'model_type'      => Category::class,
                        'model_id'        => $id,
                        'old_values'      => $oldData,
                        'new_values'      => null,
                        'request_payload' => RequestAuditHelper::sanitize(request()),
                        'ip_address'      => request()->ip(),
                        'user_agent'      => request()->userAgent(),
                    ]);
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
            Log::error($e->getMessage());
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
                $data = Category::find($id);
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
        return $this->exportAsFormat($request, 'xlsx', 'category.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request, 'csv', 'category.csv');
    }

    public function exportPdf(Request $request)
    {
        $data = $this->getCategoryData($request);

        $pdf = Pdf::loadView('accounting::master.category', ['arrData' => $data]);
        return $pdf->download('category.pdf');
    }

    /* ---------- Shared Methods ---------- */

    private function exportAsFormat(Request $request, string $format, string $filename)
    {
        $data = $this->getCategoryData($request);
        return Excel::download(new CategoryExport($data), $filename);
    }

    private function getCategoryData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params); // $search, $page, $categoryType

        return $this->categoryRepo->getAllDataBy($search, $page, 100);
    }

}
