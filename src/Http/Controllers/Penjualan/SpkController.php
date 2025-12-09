<?php

namespace Icso\Accounting\Http\Controllers\Penjualan;

use Icso\Accounting\Exports\SalesSpkExport;
use Icso\Accounting\Exports\SalesSpkReportExport;
use Icso\Accounting\Http\Requests\CreateSalesSpkRequest;
use Icso\Accounting\Repositories\Penjualan\Spk\SpkRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class SpkController extends Controller
{
    protected $salesSpkRepo;

    public function __construct(SpkRepo $salesSpkRepo)
    {
        $this->salesSpkRepo = $salesSpkRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $vendorId = $request->vendor_id;

        $where = array();
        if(!empty($fromDate) && !empty($untilDate)){
            $where[] = array(
                'method' => 'whereBetween',
                'value' => array('field' => 'spk_date', 'value' => [$fromDate,$untilDate]));
        }

        if(!empty($vendorId)){
            $where[] = ['vendor_id', '=', $vendorId];
        }
        return compact('search', 'page', 'perpage', 'where', 'fromDate','untilDate','vendorId');
    }

    public function getAllData(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->salesSpkRepo->getAllDataBy($search,$page,$perpage,$where);
        $total = $this->salesSpkRepo->getAllTotalDataBy($search, $where);
        $hasMore = Helpers::hasMoreData($total, $page, $data);
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
        }
        return response()->json($this->data);
    }

    public function store(CreateSalesSpkRequest $request): \Illuminate\Http\JsonResponse
    {
        $res = $this->salesSpkRepo->store($request);
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

    public function show(Request $request): \Illuminate\Http\JsonResponse
    {
        $res = $this->salesSpkRepo->findOne($request->id,array(),['vendor','order','spkproduct','spkproduct.orderproduct','spkproduct.product']);
        if($res){
            if(!empty($res->spkproduct)){
                foreach ($res->spkproduct as $item){
                    $countDelivered = $this->salesSpkRepo->getSpkProduct($item->spk_id,$item->product_id);
                    $item->qty_delivered = $countDelivered;
                }
            }
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        }else {
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
            $this->salesSpkRepo->deleteAdditional($id);
            $this->salesSpkRepo->delete($id);
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
                    $this->salesSpkRepo->deleteAdditional($id);
                    $this->salesSpkRepo->delete($id);
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
        $total = $this->salesSpkRepo->getAllTotalDataBy($search, $where);
        $data = $this->salesSpkRepo->getAllDataBy($search,$page,$total,$where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request,'spk-penjualan.xlsx');
    }

    public function exportCsv(Request $request){
        return $this->exportAsFormat($request,'spk-penjualan.csv');
    }


    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new SalesSpkExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new SalesSpkExport($data);
        $pdf = PDF::loadView('accounting::sales.sales_spk_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('spk-penjualan.pdf');
    }

    public function exportReportExcel(Request $request)
    {
        return $this->exportReportAsFormat($request,'laporan-spk-jasa.xlsx');
    }

    public function exportReportCsv(Request $request){
        return $this->exportReportAsFormat($request,'laporan-spk-jasa.csv');
    }

    public function exportReportPdf(Request $request){
        return $this->exportReportAsFormat($request,'laporan-spk-jasa.pdf', 'pdf');
    }

    private function exportReportAsFormat(Request $request, string $filename,string $type = 'excel')
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->salesSpkRepo->getAllDataBy($search, $page, $perpage, $where);
        if($type == 'excel'){
            return $this->downloadExcel($data, $params, $filename);
        } else {
            return $this->downloadPdf($request, $data, $params, $filename);
        }
    }

    private function downloadExcel($data, $params, $filename){
        return Excel::download(new SalesSpkReportExport($data,$params), $filename);
    }

    private function downloadPdf(Request $request, $data, $params, $filename){
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::sales.sales_spk_report_detail', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }
}
