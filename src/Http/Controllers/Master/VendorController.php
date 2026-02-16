<?php
namespace Icso\Accounting\Http\Controllers\Master;


use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\SampleVendorExport;
use Icso\Accounting\Exports\VendorExport;
use Icso\Accounting\Http\Requests\CreateVendorRequest;
use Icso\Accounting\Imports\VendorImport;
use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Repositories\Master\Vendor\VendorRepo;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\VendorType;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class VendorController extends Controller
{
    protected $vendorRepo;

    public function __construct(VendorRepo $vendorRepo)
    {
        $this->vendorRepo = $vendorRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $vendorType = $request->vendor_type;
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        return compact('search', 'page', 'perpage', 'vendorType');
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->vendorRepo->getAllDataBy($search,$page,$perpage,array('vendor_type' => $vendorType));
        $total = $this->vendorRepo->getAllTotalDataBy($search,array('vendor_type' => $vendorType));
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

    public function store(CreateVendorRequest $request)
    {
        $res = $this->vendorRepo->store($request);
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
        $data = $this->vendorRepo->findOne($id,[],['coa','vendor_meta']);
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
            $data = Vendor::find($id);
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
            echo $e->getMessage();
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
                $data = Vendor::find($id);
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

    public function getCountVendor(Request $request): \Illuminate\Http\JsonResponse
    {
        $vendorType = $request->input('vendor_type'); // Use input() to get request data
        $query = Vendor::query(); // Use query() to build the query

        if (!empty($vendorType)) {
            $query->where('vendor_type', $vendorType);
        }

        $vendorCount = $query->count(); // Execute the count query

        $response = [
            'status' => true,
            'message' => 'Data berhasil ditemukan',
            'data' => $vendorCount
        ];

        return response()->json($response);
    }

    public function downloadSample(Request $request)
    {
        $vendorType = $request->vendor_type;
        return Excel::download(new SampleVendorExport(), $vendorType == VendorType::SUPPLIER ? 'sample_supplier.xlsx' : 'sample_customer.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        $userId = $request->user_id;
        $vendorType = $request->vendor_type;
        $import = new VendorImport($userId, $vendorType);
        Excel::import($import, $request->file('file'));

        $success = $import->getSuccessCount();
        $errors = $import->getErrors();
        $results = $import->getRowResults();
        $totalRow = count($errors) + $success;

        if($errors){
            return response()->json(['status' => false, 'success' => $success,'messageError' => $errors,'errors' => count($errors), 'imported' => $import->getTotalRows()]);
        }
        return response()->json(['status' => true,'success' => $success,'errors' => count($errors), 'message' => 'File berhasil import', 'imported' => $import->getTotalRows()], 200);

    }

    private function getVendorData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params); // $search, $page, $categoryType


        return $this->vendorRepo->getAllDataBy($search, $page, 100,array('vendor_type' => $vendorType));
    }

    public function export(Request $request)
    {
        $vendorType = $request->vendor_type;
        $fileName = "customer.xlsx";
        if($vendorType == VendorType::SUPPLIER){
            $fileName = "supplier.xlsx";
        }
        return $this->exportAsFormat($request, $fileName);
    }

    public function exportCsv(Request $request)
    {
        $vendorType = $request->vendor_type;
        $fileName = "customer.csv";
        if($vendorType == VendorType::SUPPLIER){
            $fileName = "supplier.csv";
        }
        return $this->exportAsFormat($request, $fileName);
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->getVendorData($request);
        return Excel::download(new VendorExport($data,$request->vendor_type), $filename);
    }

    public function exportPdf(Request $request)
    {
        $vendorType = $request->vendor_type;
        $fileName = "customer.pdf";
        if($vendorType == VendorType::SUPPLIER){
            $fileName = "supplier.pdf";
        }
        $data = $this->getVendorData($request);

        $pdf = Pdf::loadView('accounting::master.vendor', ['arrData' => $data, 'vendorType' => $vendorType])->setPaper('A4', 'landscape');;
        return $pdf->download($fileName);
    }

}
