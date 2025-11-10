<?php

namespace Icso\Accounting\Http\Controllers\Penjualan;

use Icso\Accounting\Exports\SalesDownPaymentExport;
use Icso\Accounting\Exports\SalesDownPaymentReportExport;
use Icso\Accounting\Http\Requests\CreateSalesDpRequest;
use Icso\Accounting\Repositories\Penjualan\Downpayment\DpRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class DpController extends Controller
{
    protected $dpRepo;

    public function __construct(DpRepo $dpRepo)
    {
        $this->dpRepo = $dpRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $vendorId = $request->vendor_id;
        $orderId = $request->order_id;
        $dpType = $request->dp_type;

        $where = array();
        if (!empty($vendorId)) {
            $where[] = array(
                'method' => 'where',
                'value' => [['vendor_id','=',$vendorId]]);
        }
        if (!empty($orderId)) {
            $where[] = array(
                'method' => 'where',
                'value' => [['order_id','=',$orderId]]);
        }
        if (!empty($dpType)) {
            $where[] = array(
                'method' => 'where',
                'value' => [['dp_type','=',$dpType]]);
        }
        if (!empty($fromDate) && !empty($untilDate)) {
            $where[] = array(
                'method' => 'whereBetween',
                'value' => array('field' => 'downpayment_date', 'value' => [$fromDate,$untilDate]));
        }
        if(!empty($dpStatus)){
            $where[] = ['downpayment_status', '=', $dpStatus];
        }
        return compact('search', 'page', 'perpage', 'where');
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->dpRepo->getAllDataBy($search,$page,$perpage,$where);
        $total = $this->dpRepo->getAllTotalDataBy($search, $where);
        $hasMore = Helpers::hasMoreData($total,$page,$data);
        if(count($data) > 0) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['has_more'] = $hasMore;
            $this->data['total'] = $total;
        }
        else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }
    public function store(CreateSalesDpRequest $request): \Illuminate\Http\JsonResponse
    {
        $res = $this->dpRepo->store($request);
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
        $res = $this->dpRepo->findOne($request->id,array(),['order', 'coa', 'order.vendor','tax','tax.taxgroup']);
        if($res){
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

    public function destroy(Request $request)
    {
        $id = $request->id;
        DB::beginTransaction();
        try
        {
            $this->dpRepo->deleteAdditional($id);
            $this->dpRepo->delete($id);
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
                    $this->dpRepo->deleteAdditional($id);
                    $this->dpRepo->delete($id);
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

    private function prepareExportData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $total = $this->dpRepo->getAllTotalDataBy($search, $where);
        $data = $this->dpRepo->getAllDataBy($search,$page,$total,$where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request,'uang-muka-penjualan.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request,'uang-muka-penjualan.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new SalesDownPaymentExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new SalesDownPaymentExport($data);
        $pdf = PDF::loadView('accounting::sales.sales_downpayment_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('uang-muka-penjualan.pdf');
    }

    private function exportReportAsFormat(Request $request, string $filename,string $type = 'excel')
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->dpRepo->getAllDataBy($search,$page,$total,$where);
        if($type == 'excel'){
            return $this->downloadExcel($data, $params, $filename);
        } else {
            return $this->downloadPdf($request, $data, $params, $filename);
        }
    }

    private function downloadExcel($data, $params, $filename){
        return Excel::download(new SalesDownPaymentReportExport($data,$params), $filename);
    }

    private function downloadPdf(Request $request, $data, $params, $filename){
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::sales.sales_downpayment_report', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    public function exportReportExcel(Request $request)
    {
        return $this->exportReportAsFormat($request,'laporan-uangmuka-penjualan.xlsx');
    }

    public function exportReportCsv(Request $request){
        return $this->exportReportAsFormat($request,'laporan-uangmuka-penjualan.csv');
    }

    public function exportReportPdf(Request $request){
        return $this->exportReportAsFormat($request,'laporan-uangmuka-penjualan.pdf', 'pdf');
    }

}
