<?php

namespace Icso\Accounting\Http\Controllers\AsetTetap\Pembelian;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\PurchasePaymentAsetTetapExport;
use Icso\Accounting\Exports\PurchasePaymentAsetTetapReportExport;
use Icso\Accounting\Http\Requests\CreatePurchasePaymentAsetTetapRequest;
use Icso\Accounting\Repositories\AsetTetap\Pembelian\PaymentRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\InputType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class PurchasePaymentController extends Controller
{
    protected $paymentRepo;

    public function __construct(PaymentRepo $paymentRepo)
    {
        $this->paymentRepo = $paymentRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $paymentMethodId = $request->payment_method_id;
        $paymentType = !empty($request->payment_type) ? $request->payment_type : InputType::PURCHASE;
        $where = array();
        $where[] = array(
            'method' => 'where',
            'value' => [['payment_type', '=', $paymentType]]
        );
        if (!empty($paymentMethodId)) {
            $where[] = array(
                'method' => 'where',
                'value' => [['payment_method_id', '=', $paymentMethodId]]
            );
        }
        return compact('search', 'page', 'perpage', 'where');
    }

    public function getAllData(Request $request):JsonResponse
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->paymentRepo->getAllDataBy($search, $page, $perpage, $where);
        $total = $this->paymentRepo->getAllTotalDataBy($search, $where);
        $hasMore = Helpers::hasMoreData($total, $page, $data);
        if (count($data) > 0) {
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

    public function store(CreatePurchasePaymentAsetTetapRequest $request): JsonResponse
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
        $res = $this->paymentRepo->findOne($request->id,array(),['payment_method','sales_invoice','sales_invoice.asettetap','invoice','invoice.order','invoice.order.aset_tetap_coa', 'invoice.order.dari_akun_coa','invoice.order.akumulasi_penyusutan_coa','invoice.order.penyusutan_coa']);
        if($res){
            if(!empty($res->invoice)){
                $val = $res->invoice;
                if(!empty($val))
                {
                    $paid = $this->paymentRepo->getTotalPaymentByInvoice($val->id);
                    $res->paid = $paid;
                    $sisa = $val->total_tagihan - $paid;
                    $res->left_bill = $sisa;
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
        $total = $this->purchaseInvoiceRepo->getAllTotalDataBy($search, $where);
        $data = $this->purchaseInvoiceRepo->getAllDataBy($search,$page,$total,$where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request, 'pembayaran-pembelian-aset-tetap.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request, 'pembayaran-pembelian-aset-tetap.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new PurchasePaymentAsetTetapExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new PurchasePaymentAsetTetapExport($data);
        $pdf = PDF::loadView('accounting::fixasset.purchase_order_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('order-pembelian-aset-tetap.pdf');
    }

    private function exportReportAsFormat(Request $request, string $filename,string $type = 'excel')
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->purchaseInvoiceRepo->getAllDataBy($search, $page, $perpage, $where);
        if($type == 'excel'){
            return $this->downloadExcel($data, $params, $filename);
        } else {
            return $this->downloadPdf($request, $data, $params, $filename);
        }
    }

    private function downloadExcel($data, $params, $filename){
        return Excel::download(new PurchasePaymentAsetTetapReportExport($data,$params), $filename);
    }

    private function downloadPdf(Request $request, $data, $params, $filename){
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::fixasset.purchase_payment_report', [
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
        return $this->exportReportAsFormat($request,'laporan-pembayaran-pembelian-aset-tetap.xlsx');
    }

    public function exportReportCsv(Request $request){
        return $this->exportReportAsFormat($request,'laporan-pembayaran-pembelian-aset-tetap.csv');
    }

    public function exportReportPdf(Request $request){
        return $this->exportReportAsFormat($request,'laporan-pembayaran-pembelian-aset-tetap.pdf', 'pdf');
    }
}
