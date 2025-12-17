<?php

namespace Icso\Accounting\Http\Controllers\Pembelian;


use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\PurchaseBastExport;
use Icso\Accounting\Exports\PurchaseBastReportExport;
use Icso\Accounting\Http\Requests\CreatePurchaseBastRequest;
use Icso\Accounting\Repositories\Pembelian\Bast\BastRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class BastController extends Controller
{
    protected $purchaseBastRepo;

    public function __construct(BastRepo $purchaseBastRepo)
    {
        $this->purchaseBastRepo = $purchaseBastRepo;
    }

    public function getAllData(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->purchaseBastRepo->getAllDataBy($search,$page,$perpage,$where);
        $total = $this->purchaseBastRepo->getAllTotalDataBy($search, $where);
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
                'value' => array('field' => 'bast_date', 'value' => [$fromDate,$untilDate]));
        }

        if(!empty($vendorId)){
            $where[] = ['vendor_id', '=', $vendorId];
        }
        return compact('search', 'page', 'perpage', 'where', 'fromDate', 'untilDate');
    }

    public function store(CreatePurchaseBastRequest $request): \Illuminate\Http\JsonResponse
    {
        $res = $this->purchaseBastRepo->store($request);
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
        $res = $this->purchaseBastRepo->findOne($request->id,array(),['vendor','order','bastproduct','bastproduct.orderproduct','bastproduct.tax']);
        if($res){
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
            $this->purchaseBastRepo->deleteAdditional($id);
            $this->purchaseBastRepo->delete($id);
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
                    $this->purchaseBastRepo->deleteAdditional($id);
                    $this->purchaseBastRepo->delete($id);
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
        $total = $this->purchaseBastRepo->getAllTotalDataBy($search, $where);
        $data = $this->purchaseBastRepo->getAllDataBy($search,$page,$total,$where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request,'bast-pembelian.xlsx');
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new PurchaseBastExport($data);
        $pdf = PDF::loadView('accounting::purchase.purchase_bast_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('bast-pembelian.pdf');
    }

    public function exportReportExcel(Request $request)
    {
        return $this->exportReportAsFormat($request,'excel-purchase-bast.xlsx');
    }

    public function exportReportCsv(Request $request)
    {
        return $this->exportReportAsFormat($request,'excel-purchase-bast.csv');
    }

    public function exportCsv(Request $request){
        return $this->exportAsFormat($request,'bast-pembelian.csv');
    }

    private function exportAsFormat(Request $request, $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new PurchaseBastExport($data), $filename);
    }

    private function exportReportAsFormat(Request $request, string $filename)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->purchaseBastRepo->getAllDataBy($search,$page,$perpage,$where);
        return Excel::download(new PurchaseBastReportExport($data,$params), $filename);
    }

    public function exportReportPdf(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->purchaseBastRepo->getAllDataBy($search,$page,$perpage,$where);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::purchase.purchase_bast_detail_report', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream('laporan-bast-pembelian.pdf');
        }

        return $pdf->download('laporan-bast-pembelian.pdf');
    }
}
