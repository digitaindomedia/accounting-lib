<?php

namespace Icso\Accounting\Http\Controllers\AsetTetap\Penjualan;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\SalesInvoiceExport;
use Icso\Accounting\Http\Requests\CreateSalesInvoiceAsetTetapRequest;
use Icso\Accounting\Repositories\AsetTetap\Penjualan\SalesInvoiceRepo;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class SalesInvoiceController extends Controller
{
    protected $salesInvoiceRepo;

    public function __construct(SalesInvoiceRepo $salesInvoiceRepo)
    {
        $this->salesInvoiceRepo = $salesInvoiceRepo;
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
                'value' => [['sales_status', '=', $status]]
            );
        }
        if(!empty($fromDate) && !empty($untilDate)){
            $where[] = array(
                'method' => 'whereBetween',
                'value' => array('field' => 'sales_date', 'value' => [$fromDate,$untilDate]));
        }
        return compact('search', 'page', 'perpage', 'where');
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->salesInvoiceRepo->getAllDataBy($search,$page,$perpage,$where);
        $total = $this->salesInvoiceRepo->getAllTotalDataBy($search, $where);
        $has_more = false;
        $page = $page + count($data);
        if($total > $page)
        {
            $has_more = true;
        }
        if(count($data) > 0) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['has_more'] = $has_more;
            $this->data['total'] = $total;
        } else{
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }

    public function store(CreateSalesInvoiceAsetTetapRequest $request){
        $res = $this->salesInvoiceRepo->store($request);
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
        $res = $this->salesInvoiceRepo->findOne($request->id,array(),['profitlosscoa','asettetap','asettetap.aset_tetap_coa', 'asettetap.dari_akun_coa','asettetap.akumulasi_penyusutan_coa','asettetap.penyusutan_coa']);
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
        $res = $this->salesInvoiceRepo->delete($request->id);
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
                $res = $this->salesInvoiceRepo->delete($id);
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
        $total = $this->salesInvoiceRepo->getAllTotalDataBy($search, $where);
        $data = $this->salesInvoiceRepo->getAllDataBy($search,$page,$total,$where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request, 'invoice-penjualan-aset-tetap.xlsx');
    }

    public function exportCsv(Request $request){
        return $this->exportAsFormat($request, 'invoice-penjualan-aset-tetap.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new SalesInvoiceExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new SalesInvoiceExport($data);
        $pdf = PDF::loadView('fixasset/sales_invoice_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('invoice-penjualan-aset-tetap.pdf');
    }
}
