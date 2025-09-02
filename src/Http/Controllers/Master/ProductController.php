<?php
namespace Icso\Accounting\Http\Controllers\Master;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\ProductExport;
use Icso\Accounting\Exports\SampleProductsExport;
use Icso\Accounting\Http\Requests\CreateProductRequest;
use Icso\Accounting\Imports\ProductsImport;
use Icso\Accounting\Models\Master\Product;
use Icso\Accounting\Models\Master\ProductConvertion;
use Icso\Accounting\Models\Master\ProductMeta;
use Icso\Accounting\Repositories\Master\Product\ProductConvertionRepo;
use Icso\Accounting\Repositories\Master\Product\ProductRepo;
use Icso\Accounting\Utils\ProductType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class ProductController extends Controller
{
    protected $productRepo;
    protected $productConvertionRepo;

    public function __construct(ProductRepo $productRepo, ProductConvertionRepo $productConvertionRepo)
    {
        $this->productRepo = $productRepo;
        $this->productConvertionRepo = $productConvertionRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;

        $filters = $request->only(['product_type', 'category_id']);
        return compact('search', 'page', 'perpage', 'filters');
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->productRepo->getAllDataBy($search,$page,$perpage,$filters);
        $total = $this->productRepo->getAllTotalDataBy($search,$filters);
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

    public function store(CreateProductRequest $request)
    {
        $res = $this->productRepo->store($request);
        if($res['status'])
        {
            $this->data['status'] = true;
            $this->data['message'] = $res['message'];

        }else{
            $this->data['status'] = false;
            $this->data['message'] = $res['message'];
        }
        return response()->json($this->data);
    }

    public function show(Request $request)
    {
        $id = $request->id;
        $data = $this->productRepo->findOne($id, array(),['unit','categories','coa','coa_biaya','productconvertion']);
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
            $data = Product::find($id);
            if($data)
            {
                if($data->canDelete()){
                    $this->productRepo->deleteAdditional($id);
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
                $data = Product::find($id);
                if($data)
                {
                    if($data->canDelete()) {
                        $this->productRepo->deleteAdditional($id);
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

    public function getAllProductConvertion(Request $request){
        $productId = $request->product_id;
        $res = $this->productConvertionRepo->findAllByWhere(array('product_id' => $productId), array(),['product','unit','base_unit']);
        if(count($res) > 0){
            $this->data['status'] = true;
            $this->data['message'] = 'data berhasil ditemukan';
            $this->data['data'] = $res;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'data gagal ditemukan';
            $this->data['data'] = '';
        }
        return response()->json($this->data);
    }

    public function storeProductConvertion(Request $request){
        $productConvertion = json_decode(json_encode($request->product_convertion));
        $productId = $request->product_id;
        DB::beginTransaction();
        try {
            if (count($productConvertion) > 0) {
                $this->productConvertionRepo->deleteByWhere(array('product_id' => $productId));
                foreach ($productConvertion as $i => $item) {
                    $arrData = array(
                        'product_id' => $productId,
                        'unit_id' => $item->unit_id,
                        'nilai' => $item->nilai,
                        'nilai_terkecil' => 0,
                        'base_unit_id' => $item->base_unit_id,
                        'price' => 0
                    );
                    ProductConvertion::create($arrData);
                }
                $resConv = ProductConvertion::where(array('product_id' => $productId))->get();
                if(count($resConv) > 0){
                    foreach ($resConv as $val){
                        $conValue = $this->productConvertionRepo->convertToSmallestUnit($val->nilai,$val->base_unit_id,$productId);
                        if(!empty($conValue)){
                            ProductConvertion::where(array('id' => $val->id))->update(array('nilai_terkecil' => $conValue));
                        }
                    }
                }
            }
            DB::commit();
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

    public function getCountProduct(Request $request)
    {
        $productType = $request->input('product_type'); // Use input() to get request data
        $query = Product::query(); // Use query() to build the query

        if (!empty($productType)) {
            $query->where('product_type', $productType);
        }

        $productCount = $query->count(); // Execute the count query

        $response = [
            'status' => true,
            'message' => 'Data berhasil ditemukan',
            'data' => $productCount
        ];

        return response()->json($response);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        $userId = $request->user_id;
        $productType = $request->product_type;
        $import = new ProductsImport($userId, $productType);
        Excel::import($import, $request->file('file'));

        $success = $import->getSuccessCount();
        $errors = $import->getErrors();
        $results = $import->getRowResults();
        $totalRow = count($errors) + $success;

        return response()->json([
            'success_count' => $success,
            'total_row' => $totalRow,
            'failed_count' => count($errors),
            'results' => $results,
            'message' => "Import selesai. Berhasil: {$success}, Gagal: " . count($errors)
        ]);
    }

    public function downloadSample(Request $request)
    {
        $productType = $request->product_type;
        return Excel::download(new SampleProductsExport($productType), $productType == ProductType::ITEM ? 'sample_barang.xlsx' : 'sample_jasa.xlsx');
    }

    public function downloadimage(Request $request)
    {
        $baseUrl = url('storage/'.tenant()->id.'/');
        $res = ProductMeta::where('id', $request->id)->first();

        // Get the file's content
        $fileContent = file_get_contents($baseUrl ."/".$res->meta_value);

        // Return response as a blob
        return Response::make($fileContent, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'attachment; filename="example.jpg"',
            'Content-Length' => strlen($fileContent)
        ]);
    }

    public function export(Request $request)
    {
        $productType = $request->product_type;
        $fileName = "barang.xlsx";
        if($productType == ProductType::SERVICE){
            $fileName = "jasa.xlsx";
        }
        return $this->exportAsFormat($request, $fileName);
    }

    public function exportCsv(Request $request)
    {
        $productType = $request->product_type;
        $fileName = "barang.csv";
        if($productType == ProductType::SERVICE){
            $fileName = "jasa.csv";
        }
        return $this->exportAsFormat($request, $fileName);
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->getProductData($request);
        return Excel::download(new ProductExport($data,$request->product_type), $filename);
    }

    public function exportPdf(Request $request)
    {
        $productType = $request->product_type;
        $fileName = "barang.pdf";
        if($productType == ProductType::SERVICE){
            $fileName = "jasa.pdf";
        }
        $data = $this->getProductData($request);

        $pdf = Pdf::loadView('master.product', ['arrData' => $data, 'productType' => $productType]);
        return $pdf->download($fileName);
    }

    private function getProductData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params); // $search, $page, $categoryType
        $filters = $request->only(['product_type']);

        return $this->productRepo->getAllDataBy($search, $page, 100, $filters);
    }

}
