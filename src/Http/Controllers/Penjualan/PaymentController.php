<?php

namespace Icso\Accounting\Http\Controllers\Penjualan;

use Icso\Accounting\Exports\SalesPaymentExport;
use Icso\Accounting\Exports\SalesPaymentReportDetailExport;
use Icso\Accounting\Http\Requests\CreateSalesPaymentRequest;
use Icso\Accounting\Repositories\Penjualan\Payment\PaymentInvoiceRepo;
use Icso\Accounting\Repositories\Penjualan\Payment\PaymentRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PaymentController extends Controller
{
    protected $paymentRepo;
    protected $paymentInvoiceRepo;

    public function __construct(PaymentRepo $paymentRepo, PaymentInvoiceRepo $paymentInvoiceRepo)
    {
        $this->paymentRepo = $paymentRepo;
        $this->paymentInvoiceRepo = $paymentInvoiceRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $vendorId = $request->vendor_id;
        $paymentMethodId = $request->payment_method_id;
        $where=array();
        if(!empty($vendorId)){
            $where[] = array(
                'method' => 'where',
                'value' => [['vendor_id','=',$vendorId]]);
        }
        if(!empty($paymentMethodId)){
            $where[] = array(
                'method' => 'where',
                'value' => [['payment_method_id','=',$paymentMethodId]]);

        }
        return compact('search', 'page', 'perpage', 'where');
    }

    public function getAllData(Request $request):JsonResponse
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->paymentRepo->getAllDataBy($search, $page, $perpage,$where);
        $total = $this->paymentRepo->getAllTotalDataBy($search, $where);
        $hasMore = Helpers::hasMoreData($total, $page, $data);
        if(count($data) > 0) {
            foreach ($data as $res){
                if(!empty($res->invoice)){
                    foreach ($res->invoice as $item){
                        $val = $item->salesinvoice;
                        if(!empty($val))
                        {
                            $paid = $this->paymentInvoiceRepo->getAllPaymentByInvoiceId($val->id);
                            $item->id = $val->id;
                            $item->nominal_paid = $item->total_payment;
                            $item->total_kurang_bayar = $item->total_discount;
                            $item->total_lebih_bayar = $item->total_overpayment;
                            $item->coa_lebih_bayar = !empty($item->coa_id_overpayment) ? json_decode($item->coa_id_overpayment) : $item->coa_id_overpayment;
                            $item->coa_kurang_bayar = !empty($item->coa_id_discount) ? json_decode($item->coa_id_discount) : $item->coa_id_discount;
                            $item->grandtotal = $val->grandtotal;
                            $terbayar = $paid - (($item->total_payment + $item->total_discount) - $item->total_overpayment);
                            $item->paid = $terbayar;
                            $sisa = $val->grandtotal - $terbayar;
                            $item->left_bill = $sisa;
                        }

                    }
                }
            }
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['has_more'] = $hasMore;
            $this->data['total'] = $total;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
            $this->data['data'] = "";
        }
        return response()->json($this->data);
    }

    public function store(CreateSalesPaymentRequest $request): JsonResponse
    {
        $res = $this->paymentRepo->store($request);
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

    public function show(Request $request): JsonResponse
    {
        $res = $this->paymentRepo->findOne($request->id,array(),['vendor','payment_method','invoice','invoice.salesinvoice','invoiceretur','invoiceretur.retur']);
        if($res){
            if(!empty($res->invoice)){
                foreach ($res->invoice as $item){
                    $val = $item->salesinvoice;
                    if(!empty($val))
                    {
                        $paid = $this->paymentInvoiceRepo->getAllPaymentByInvoiceId($val->id);
                        $item->id = $val->id;
                        $item->nominal_paid = $item->total_payment;
                        $item->total_kurang_bayar = $item->total_discount;
                        $item->total_lebih_bayar = $item->total_overpayment;
                        $item->coa_lebih_bayar = !empty($item->coa_id_overpayment) ? json_decode($item->coa_id_overpayment) : $item->coa_id_overpayment;
                        $item->coa_kurang_bayar = !empty($item->coa_id_discount) ? json_decode($item->coa_id_discount) : $item->coa_id_discount;
                        $item->grandtotal = $val->grandtotal;
                        $terbayar = $paid - (($item->total_payment + $item->total_discount) - $item->total_overpayment);
                        $item->paid = $terbayar;
                        $sisa = $val->grandtotal - $terbayar;
                        $item->left_bill = $sisa;
                    }

                }
            }
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal ditemukan";
        }
        return response()->json($this->data);
    }

    public function destroy(Request $request)
    {
        $id = $request->id;
        DB::beginTransaction();
        try
        {
            $this->paymentRepo->deleteAdditional($id);
            $this->paymentRepo->delete($id);
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
                    $this->paymentRepo->deleteAdditional($id);
                    $this->paymentRepo->delete($id);
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
        $total = $this->paymentRepo->getAllTotalDataBy($search, $where);
        $data = $this->paymentRepo->getAllDataBy($search, $page, $total,$where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request,'pembayaran-penjualan.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request,'pembayaran-penjualan.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new SalesPaymentExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new SalesPaymentExport($data);
        $pdf = PDF::loadView('accounting::sales.sales_payment_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('pembayaran-penjualan.pdf');
    }

    public function exportReportExcel(Request $request)
    {
        return $this->exportReportAsFormat($request,'laporan-pembayaran-penjualan.xlsx');
    }

    public function exportReportCsv(Request $request){
        return $this->exportReportAsFormat($request,'laporan-pembayaran-penjualan.csv');
    }

    public function exportReportPdf(Request $request){
        return $this->exportReportAsFormat($request,'laporan-pembayaran-penjualan.pdf', 'pdf');
    }

    private function exportReportAsFormat(Request $request, string $filename,string $type = 'excel')
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->paymentRepo->getAllDataBy($search, $page, $perpage, $where);
        if($type == 'excel'){
            return $this->downloadExcel($data, $params, $filename);
        } else {
            return $this->downloadPdf($request, $data, $params, $filename);
        }
    }

    private function downloadExcel($data, $params, $filename){
        return Excel::download(new SalesPaymentReportDetailExport($data,$params), $filename);
    }

    private function downloadPdf(Request $request, $data, $params, $filename){
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::sales.sales_payment_detail_report', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }
}
