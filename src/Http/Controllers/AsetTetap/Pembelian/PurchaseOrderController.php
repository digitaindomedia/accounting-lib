<?php

namespace Icso\Accounting\Http\Controllers\AsetTetap\Pembelian;

use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Exports\PurchaseOrderAsetTetapExport;
use Icso\Accounting\Exports\PurchaseOrderAsetTetapReportExport;
use Icso\Accounting\Http\Requests\CreatePurchaseOrderAsetTetapRequest;
use Icso\Accounting\Repositories\AsetTetap\Pembelian\OrderRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\TransactionsCode;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PurchaseOrderController extends Controller
{
    protected $purchaseOrderRepo;

    public function __construct(OrderRepo $purchaseOrderRepo)
    {
        $this->purchaseOrderRepo = $purchaseOrderRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $from = $request->from;
        $where = array();
        if(!empty($from)) {
            if ($from == TransactionsCode::PENERIMAAN) {
                $where[] = array(
                    'method' => 'where',
                    'value' => [['status_aset_tetap', '=', StatusEnum::OPEN]]
                );
            }
            else if($from == TransactionsCode::UANG_MUKA_PEMBELIAN_ASET_TETAP){
                $where[] = array(
                    'method' => 'where',
                    'value' => [['status_aset_tetap','!=',StatusEnum::INVOICE]]
                );
            }
            else if($from == TransactionsCode::INVOICE_PEMBELIAN_ASET_TETAP){
                $where[] = array(
                    'method' => 'where',
                    'value' => [['status_aset_tetap','=',StatusEnum::PENERIMAAN]]
                );
            }
        }
        if(!empty($fromDate) && !empty($untilDate)){
            $where[] = array(
                'method' => 'whereBetween',
                'value' => array('field' => 'aset_tetap_date', 'value' => [$fromDate,$untilDate]));
        }
        return compact('search', 'page', 'perpage', 'where','fromDate','untilDate');
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->purchaseOrderRepo->getAllDataBy($search,$page,$perpage,$where);
        $total = $this->purchaseOrderRepo->getAllTotalDataBy($search, $where);
        $hasMore = Helpers::hasMoreData($total, $page, $data);
        if(count($data) > 0) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['has_more'] = $hasMore;
            $this->data['total'] = $total;
        } else{
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }

    public function store(CreatePurchaseOrderAsetTetapRequest $request){
        $res = $this->purchaseOrderRepo->store($request);
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
        $res = $this->purchaseOrderRepo->findOne($request->id,array(),['aset_tetap_coa', 'dari_akun_coa','akumulasi_penyusutan_coa','penyusutan_coa','downpayment']);
        if($res){
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal ditemukan";
            $this->data['data'] = '';
        }
        return response()->json($this->data);
    }

    public function delete(Request $request){
        $res = $this->purchaseOrderRepo->delete($request->id);
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
                $res = $this->purchaseOrderRepo->delete($id);
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

    private function prepareExportData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $total = $this->purchaseOrderRepo->getAllTotalDataBy($search, $where);
        $data = $this->purchaseOrderRepo->getAllDataBy($search,$page,$total,$where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request, 'order-pembelian-aset-tetap.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request, 'order-pembelian-aset-tetap.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new PurchaseOrderAsetTetapExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new PurchaseOrderAsetTetapExport($data);
        $pdf = PDF::loadView('accounting::fixasset.purchase_order_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('order-pembelian-aset-tetap.pdf');
    }

    private function exportReportAsFormat(Request $request, string $filename,string $type = 'excel')
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->purchaseOrderRepo->getAllDataBy($search, $page, $perpage, $where);
        if($type == 'excel'){
            return $this->downloadExcel($data, $params, $filename);
        } else {
            return $this->downloadPdf($request, $data, $params, $filename);
        }
    }

    private function downloadExcel($data, $params, $filename){
        return Excel::download(new PurchaseOrderAsetTetapReportExport($data,$params), $filename);
    }

    private function downloadPdf(Request $request, $data, $params, $filename){
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::fixasset.purchase_order_report', [
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
        return $this->exportReportAsFormat($request,'laporan-order-pembelian-aset-tetap.xlsx');
    }

    public function exportReportCsv(Request $request){
        return $this->exportReportAsFormat($request,'laporan-order-pembelian-aset-tetap.csv');
    }

    public function exportReportPdf(Request $request){
        return $this->exportReportAsFormat($request,'laporan-order-pembelian-aset-tetap.pdf', 'pdf');
    }
}
