<?php

namespace Icso\Accounting\Http\Controllers\AsetTetap\Pembelian;


use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\PurchaseDpAsetTetapExport;
use Icso\Accounting\Http\Requests\CreatePurchaseDownPaymentAsetTetapRequest;
use Icso\Accounting\Repositories\AsetTetap\Pembelian\DownPaymentRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class PurchaseDownPaymentController extends Controller
{
    protected $dpRepo;

    public function __construct(DownPaymentRepo $dpRepo)
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
        $orderId = $request->order_id;
        $dpStatus = $request->status;

        $where = array();
        if(!empty($orderId)){
            $where[] = array(
                'method' => 'where',
                'value' => [['order_id', '=', $orderId]]);
        }
        if(!empty($dpStatus)){
            $where[] = array(
                'method' => 'where',
                'value' => [['downpayment_status', '=', $dpStatus]]);
        }
        if(!empty($fromDate) && !empty($untilDate)){
            $where[] = array(
                'method' => 'whereBetween',
                'value' => array('field' => 'downpayment_date', 'value' => [$fromDate,$untilDate]));
        }
        return compact('search', 'page', 'perpage', 'where');
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->dpRepo->getAllDataBy($search,$page,$perpage,$where);
        $total = $this->dpRepo->getAllTotalDataBy($search, $where);
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
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }

    public function store(CreatePurchaseDownPaymentAsetTetapRequest $request){
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
        $res = $this->dpRepo->findOne($request->id,array(),['order', 'coa', 'tax', 'tax.taxgroup','tax.taxgroup.tax']);
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

    public function delete(Request $request){
        $res = $this->dpRepo->deleteData($request->id);
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
                $res = $this->dpRepo->deleteData($id);
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
        $total = $this->dpRepo->getAllTotalDataBy($search, $where);
        $data = $this->dpRepo->getAllDataBy($search,$page,$total,$where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request, 'uang-muka-pembelian-aset-tetap.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request, 'uang-muka-pembelian-aset-tetap.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new PurchaseDpAsetTetapExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new PurchaseDpAsetTetapExport($data);
        $pdf = PDF::loadView('fixasset/purchase_downpayment_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('uang-muka-pembelian-aset-tetap.pdf');
    }
}
