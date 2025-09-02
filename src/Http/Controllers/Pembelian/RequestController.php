<?php

namespace Icso\Accounting\Http\Controllers\Pembelian;


use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Exports\PurchaseRequestExport;
use Icso\Accounting\Exports\PurchaseRequestReportDetailExport;
use Icso\Accounting\Exports\SamplePurchaseRequestExport;
use Icso\Accounting\Http\Requests\CreatePurchaseRequestRequest;
use Icso\Accounting\Imports\PurchaseRequestImport;
use Icso\Accounting\Models\Pembelian\Permintaan\PurchaseRequest;
use Icso\Accounting\Repositories\Pembelian\Request\RequestRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class RequestController extends Controller
{
    protected $requestRepo;

    public function __construct(RequestRepo $requestRepo)
    {
        $this->requestRepo = $requestRepo;
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $filters = $this->buildFilters($request);
        $data = $this->requestRepo->getAllDataBetweenBy(
            $search,
            $page,
            $perpage,
            $filters['where'],
            $filters['whereBetween']
        );
        $total = $this->requestRepo->getAllTotalDataBetweenBy(
            $search,
            $filters['where'],
            $filters['whereBetween']
        );

        $hasMore = Helpers::hasMoreData($total,$page,$data);
        if(count($data) > 0) {
            foreach ($data as $item){
                $findAvailableProduct = $this->requestRepo->findInUseInOrder($item->id);
                $item->available_product = $findAvailableProduct['request_product'];
            }
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['has_more'] = $hasMore;
            $this->data['total'] = $total;
        }
        else {
            $this->data['status'] = false;
            $this->data['data'] = array();
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);

    }

    public function store(CreatePurchaseRequestRequest $request){
        $res = $this->requestRepo->store($request);
        if($res){
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';
            $this->data['data'] = '';
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal disimpan";
        }
        return response()->json($this->data);
    }

    public function show(Request $request){
        $res = $this->requestRepo->findOne($request->id,array(),['requestproduct','requestproduct.unit','requestproduct.product', 'requestmeta']);
        if($res){
            $transaksi = $this->requestRepo->getTransaksi($request->id);
            $res->transactions = $transaksi;
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal disimpan";
            $this->data['data'] = '';
        }
        return response()->json($this->data);
    }

    public function delete(Request $request){
        $res = $this->requestRepo->delete($request->id);
        if($res){
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil dihapus';
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal dihapus";
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
                $res = $this->requestRepo->delete($id);
                if($res){
                    $successDelete = $successDelete + 1;
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

    public function completion()
    {
        $completed = PurchaseRequest::where('request_status', StatusEnum::SELESAI)->count();
        $outstanding = PurchaseRequest::whereIn('request_status', [StatusEnum::OPEN, StatusEnum::PARSIAL_ORDER])->count();
        $all = PurchaseRequest::count();
        $arrData = [
            ['name' => 'Completed (Sudah Diorder)', 'total' => $completed],
            ['name' => 'Outstanding', 'total' => $outstanding],
            ['name' => 'Total', 'total' => $all],
        ];
        $this->data['status'] = true;
        $this->data['message'] = 'Data berhasil ditemukan';
        $this->data['data'] = $arrData;
        return response()->json($this->data);
    }

    public function downloadSample()
    {
        return Excel::download(new SamplePurchaseRequestExport(), 'sample_permintaan_pembelian.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        $userId = $request->user_id;
        $import = new PurchaseRequestImport($userId);
        Excel::import($import, $request->file('file'));

        if ($errors = $import->getErrors()) {
            return response()->json(['status' => false, 'success' => $import->getSuccessCount(),'messageError' => $errors,'errors' => count($errors), 'imported' => $import->getTotalRows()]);
        }

        return response()->json(['status' => true,'success' => $import->getSuccessCount(),'errors' => count($import->getErrors()), 'message' => 'File berhasil import', 'imported' => $import->getTotalRows()], 200);
    }

    public function export(Request $request)
    {
       return $this->exportAsFormat($request, 'permintaan-pembelian.xlsx');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $filters = $this->buildFilters($request);
        $total = $this->requestRepo->getAllTotalDataBetweenBy(
            $request->q,
            $filters['where'],
            $filters['whereBetween']
        );
        $data = $this->requestRepo->getAllDataBetweenBy(
            $request->q,
            $request->page,
            $total,
            $filters['where'],
            $filters['whereBetween']
        );
        //logger()->info('Export Data: ', $data->toArray());
        return Excel::download(new PurchaseRequestExport($data), $filename);
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request, 'permintaan-pembelian.csv');
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->buildFilters($request);
        $total = $this->requestRepo->getAllTotalDataBetweenBy(
            $request->q,
            $filters['where'],
            $filters['whereBetween']
        );
        $data = $this->requestRepo->getAllDataBetweenBy(
            $request->q,
            $request->page,
            $total,
            $filters['where'],
            $filters['whereBetween']
        );
        $export = new PurchaseRequestExport($data);
        $pdf = PDF::loadView('purchase/purchase_request_pdf', ['arrData' => $export->getData()]);

        return $pdf->download('permintaan-pembelian.pdf');
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $status = $request->status;
        $sifat = $request->sifat;
        return compact('search', 'page', 'perpage', 'status','sifat','fromDate','untilDate');
    }

    private function buildFilters(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $where = [];
        $whereBetween = [];

        if (!empty($fromDate) && !empty($untilDate)) {
            $whereBetween = [$fromDate, $untilDate];
        }

        if (!empty($status)) {
            if ($status == 'available') {
                $where[] = ['method' => 'where', 'value' => [['request_status', '=', StatusEnum::OPEN]]];
                $where[] = ['method' => 'orWhere', 'value' => [['request_status', '=', StatusEnum::PARSIAL_ORDER]]];
            } else {
                $where[] = ['method' => 'where', 'value' => [['request_status', '=', $status]]];
            }
        }

        if (!empty($sifat)) {
            $where[] = ['method' => 'where', 'value' => [['urgency', '=', $sifat]]];
        }

        return compact('where', 'whereBetween');
    }

    public function exportReportExcel(Request $request)
    {
        $params = $this->setQueryParameters($request);
        $filters = $this->buildFilters($request);
        extract($params);
        $data = $this->requestRepo->getAllDataBetweenBy(
            $search,
            $page,
            $perpage,
            $filters['where'],
            $filters['whereBetween']
        );
        return Excel::download(new PurchaseRequestReportDetailExport($data,$params), 'excel-purchase-request.xlsx');
    }

    public function exportReportPdf(Request $request)
    {
        $params = $this->setQueryParameters($request);
        $filters = $this->buildFilters($request);
        extract($params);
        $data = $this->requestRepo->getAllDataBetweenBy(
            $search,
            $page,
            $perpage,
            $filters['where'],
            $filters['whereBetween']
        );

        // Render the same Blade report view to PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('purchase/purchase_request_detail_report', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream('laporan-permintaan-pembelian.pdf');
        }

        return $pdf->download('laporan-permintaan-pembelian.pdf');
    }

}
