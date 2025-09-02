<?php

namespace Icso\Accounting\Http\Controllers\AsetTetap\Pembelian;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\PurchaseInvoiceAsetTetapExport;
use Icso\Accounting\Http\Requests\CreatePurchaseInvoiceAsetTetapRequest;
use Icso\Accounting\Repositories\AsetTetap\Pembelian\InvoiceRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class PurchaseInvoiceController extends Controller
{
    protected $purchaseInvoiceRepo;

    public function __construct(InvoiceRepo $purchaseInvoiceRepo)
    {
        $this->purchaseInvoiceRepo = $purchaseInvoiceRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $status = $request->status;
        $where = array();
        if(!empty($status)){
            $where[] = array(
                'method' => 'where',
                'value' => [['invoice_status', '=', $status]]
            );
        }
        if(!empty($fromDate) && !empty($untilDate)){
            $where[] = array(
                'method' => 'whereBetween',
                'value' => array('field' => 'aset_tetap_date', 'value' => [$fromDate,$untilDate]));
        }
        return compact('search', 'page', 'perpage', 'where');
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->purchaseInvoiceRepo->getAllDataBy($search,$page,$perpage,$where);
        $total = $this->purchaseInvoiceRepo->getAllTotalDataBy($search, $where);
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

    public function store(CreatePurchaseInvoiceAsetTetapRequest $request){
        $res = $this->purchaseInvoiceRepo->store($request);
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
        $res = $this->purchaseInvoiceRepo->findOne($request->id,array(),['order','order.aset_tetap_coa', 'order.dari_akun_coa','order.akumulasi_penyusutan_coa','order.penyusutan_coa']);
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
        $res = $this->purchaseInvoiceRepo->delete($request->id);
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
                $res = $this->purchaseInvoiceRepo->delete($id);
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
        $total = $this->purchaseInvoiceRepo->getAllTotalDataBy($search, $where);
        $data = $this->purchaseInvoiceRepo->getAllDataBy($search,$page,$total,$where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request, 'invoice-pembelian-aset-tetap.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request, 'invoice-pembelian-aset-tetap.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new PurchaseInvoiceAsetTetapExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new PurchaseInvoiceAsetTetapExport($data);
        $pdf = PDF::loadView('fixasset/purchase_invoice_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('invoice-pembelian-aset-tetap.pdf');
    }
}
