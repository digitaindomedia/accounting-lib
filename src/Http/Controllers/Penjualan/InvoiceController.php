<?php

namespace Icso\Accounting\Http\Controllers\Penjualan;

use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Exports\KartuPiutangExcelExport;
use Icso\Accounting\Exports\SalesInvoiceExport;
use Icso\Accounting\Exports\SalesInvoiceReportExport;
use Icso\Accounting\Exports\SampleSalesInvoiceExport;
use Icso\Accounting\Exports\SampleJurnalInvoiceExport;
use Icso\Accounting\Exports\JurnalInvoiceExport;
use Icso\Accounting\Http\Requests\CreateSalesInvoiceRequest;
use Icso\Accounting\Imports\SalesInvoiceImport;
use Icso\Accounting\Imports\JurnalInvoiceImport;
use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Penjualan\Invoicing\SalesInvoicing;
use Icso\Accounting\Models\Penjualan\Order\SalesOrderProduct;
use Icso\Accounting\Models\Penjualan\Pembayaran\SalesPaymentInvoice;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Icso\Accounting\Repositories\Master\Vendor\VendorRepo;
use Icso\Accounting\Repositories\Penjualan\Delivery\DeliveryRepo;
use Icso\Accounting\Repositories\Penjualan\Invoice\InvoiceRepo;
use Icso\Accounting\Repositories\Penjualan\Payment\PaymentInvoiceRepo;
use Icso\Accounting\Repositories\Penjualan\Retur\ReturRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\InputType;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\Utility;
use Icso\Accounting\Utils\VendorType;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class InvoiceController extends Controller
{
    protected $invoiceRepo;
    protected $paymentInvoiceRepo;
    protected $returRepo;

    public function __construct(InvoiceRepo $invoiceRepo, PaymentInvoiceRepo $paymentInvoiceRepo, ReturRepo $returRepo)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentInvoiceRepo = $paymentInvoiceRepo;
        $this->returRepo = $returRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $page = ($page -1) * $perpage;
        $where = $this->buildWhereClause($request);
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        return compact('search', 'page', 'perpage', 'where', 'fromDate', 'untilDate');
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->invoiceRepo->getAllDataBy($search, $page, $perpage, $where);
        $total = $this->invoiceRepo->getAllTotalDataBy($search, $where);
        $hasMore = Helpers::hasMoreData($total, $page, $data);

        $salesDeliveryRepo = new DeliveryRepo(new SalesDelivery());

        if (count($data) > 0) {
            $data = $this->processInvoiceData($data, $salesDeliveryRepo);
            $this->data = [
                'status' => true,
                'message' => 'Data berhasil ditemukan',
                'data' => $data,
                'has_more' => $hasMore,
                'total' => $total,
                'key' => $request->key ?? "all"
            ];

        } else {
            $this->data = [
                'status' => false,
                'message' => 'Data tidak ditemukan',
                'key' => $request->key ?? "all",
                'data' => []
            ];
        }
        if (!empty($request->vendor_id)) {
            $this->data['retur'] = $this->returRepo->findAllByWhere(['retur_status' => StatusEnum::OPEN, 'vendor_id' => $request->vendor_id]);
        }

        return response()->json($this->data);
    }

    protected function buildWhereClause($request)
    {
        $where = [];

        if ($request->invoice_type) {
            $where[] = ['method' => 'where', 'value' => [['invoice_type', '=', $request->invoice_type]]];
        }
        if ($request->coa_id) {
            $where[] = ['method' => 'where', 'value' => [['coa_id', '=', $request->coa_id]]];
        }
        if ($request->vendor_id) {
            $where[] = ['method' => 'where', 'value' => [['vendor_id', '=', $request->vendor_id]]];
        }
        if ($request->status) {
            $where[] = ['method' => 'where', 'value' => [['invoice_status', '=', $request->status]]];
        }
        if (isset($request->order_id) && ($request->order_id === '0' || !empty($request->order_id))) {
            $where[] = ['method' => 'where', 'value' => [['order_id', '=', $request->order_id]]];
        }
        if ($request->from_date && $request->until_date) {
            $where[] = ['method' => 'whereBetween', 'value' => ['field' => 'invoice_date', 'value' => [$request->from_date, $request->until_date]]];
        }
        if ($request->input_type) {
            $where[] = ['method' => 'where', 'value' => [['input_type', '=', $request->input_type]]];
        }

        return $where;
    }

    protected function processInvoiceData($data, $salesDeliveryRepo)
    {
        foreach ($data as $item) {
            if (!empty($item->invoicedelivery)) {
                foreach ($item->invoicedelivery as $val) {
                    $val->delivery->total = $salesDeliveryRepo->getTotalDelivery($val->delivery->id);
                }
            }
            if ($item->invoice_type == ProductType::SERVICE) {
                $item->orderproductservice = SalesOrderProduct::where(['order_id' => $item->order_id])->with(['tax', 'tax.taxgroup'])->get();
            }
            $paid = $this->paymentInvoiceRepo->getAllPaymentByInvoiceId($item->id);
            $left_bill = $item->grandtotal - $paid;
            if ($left_bill == 0) {
                InvoiceRepo::changeStatusInvoice($item->id);
            }
            $item->left_bill = $left_bill;
            $item->nominal_paid = $left_bill;
            $item->paid = $paid;
            $item->payment_list = $this->invoiceRepo->getPaymentList($item->id);
            $item->dp = $this->invoiceRepo->getDpListBy($item->id);
            $item->has_dp = count($item->dp) > 0;
            $item->dp_id = $item->has_dp ? $item->dp[0]->id : "";
        }

        return $data;
    }

    public function storeSaldoAwal(Request $request)
    {
        $coaId = $request->coa_id;
        $userId = $request->user_id;
        $invoice = json_decode(json_encode($request->invoice));
        DB::beginTransaction();
        try {
            if (count($invoice) > 0) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                foreach ($invoice as $i => $item) {
                    $req = new Request();
                    $req->coa_id = $coaId;
                    $req->user_id = $userId;
                    $req->invoice_date = date("Y-m-d", strtotime($item->invoice_date));
                    $req->invoice_no = $item->invoice_no;
                    $req->note = $item->note;
                    $req->vendor_id = $item->vendor_id;
                    $req->subtotal = Utility::remove_commas($item->nominal);
                    $req->grandtotal = Utility::remove_commas($item->nominal);
                    $req->invoice_type = ProductType::ITEM;
                    $req->input_type = InputType::SALDO_AWAL;
                    $this->invoiceRepo->store($req);
                }
            }
            DB::commit();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';
            $this->data['data'] = '';
        }catch (\Exception $e) {
            DB::rollBack();
            $this->data['status'] = false;
            $this->data['message'] = $e->getMessage();
        }
        return response()->json($this->data);
    }

    public function store(CreateSalesInvoiceRequest $request){
        $res = $this->invoiceRepo->store($request);
        if($res){
            $resData = $this->invoiceRepo->findOne($res,array(),['vendor','warehouse','invoicedelivery','invoicedelivery.delivery.warehouse','invoicedelivery.delivery.deliveryproduct','invoicedelivery.delivery.deliveryproduct.unit','invoicedelivery.delivery.deliveryproduct.product','invoicedelivery.delivery.deliveryproduct.tax','invoicedelivery.delivery.deliveryproduct.tax.taxgroup','invoicedelivery.delivery.deliveryproduct.tax.taxgroup.tax','order','warehouse','vendor','orderproduct', 'orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup','orderproduct.tax.taxgroup.tax','orderproduct.unit']);
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';
            $this->data['data'] = $resData;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal disimpan";
        }
        return response()->json($this->data);
    }

    public function storeJurnal(Request $request)
    {
        $coaId = $request->coa_id;
        $userId = $request->user_id;
        $invoice = json_decode(json_encode($request->invoice));
        if(!empty($invoice)){
            $req = new Request();
            $req->coa_id = $coaId;
            $req->user_id = $userId;
            $req->invoice_date = date("Y-m-d", strtotime($invoice->invoice_date));
            $req->invoice_no = $invoice->invoice_no;
            $req->note = $invoice->note;
            $req->vendor_id = $invoice->vendor_id;
            $req->subtotal = Utility::remove_commas($invoice->nominal);
            $req->grandtotal = Utility::remove_commas($invoice->nominal);
            $req->invoice_type = ProductType::ITEM;
            $req->input_type = InputType::JURNAL;
            $res = $this->invoiceRepo->store($req);
            if($res){
                $this->data['status'] = true;
                $this->data['message'] = 'Data berhasil disimpan';
                $this->data['data'] = '';
            } else {
                $this->data['status'] = false;
                $this->data['message'] = 'Data Gagal Disimpan';
            }
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data Gagal Disimpan';
        }
        return response()->json($this->data);
    }

    public function updateSaldoAwal(Request $request)
    {
        $userId = $request->user_id;
        $id = $request->id;
        $invoiceDate = $request->invoice_date;
        $invoiceNo = $request->invoice_no;
        $vendorId = $request->vendor_id;
        $note = !empty($request->note) ? $request->note : '';
        $nominal = Utility::remove_commas($request->nominal);
        $arrData = array(
            'invoice_date' => $invoiceDate,
            'invoice_no' => $invoiceNo,
            'note' => $note,
            'subtotal' => $nominal,
            'grandtotal' => $nominal,
        );
        $res = $this->invoiceRepo->update($arrData,$id);
        if($res){
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';
            $this->data['data'] = '';
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal disimpan';
        }
        return response()->json($this->data);
    }

    public function deleteById(Request $request){
        $id = $request->id;
        $res = $this->invoiceRepo->delete($id);
        if($res) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil dihapus';
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
        }
        return response()->json($this->data);
    }

    public function show(Request $request){
        $salesDeliveryRepo = new DeliveryRepo(new SalesDelivery());
        $id = $request->id;
        $res = $this->invoiceRepo->findOne($id,array(),['vendor','warehouse','invoicedelivery','invoicedelivery.delivery.warehouse','invoicedelivery.delivery.deliveryproduct','invoicedelivery.delivery.deliveryproduct.unit','invoicedelivery.delivery.deliveryproduct.product','invoicedelivery.delivery.deliveryproduct.tax','invoicedelivery.delivery.deliveryproduct.tax.taxgroup','invoicedelivery.delivery.deliveryproduct.tax.taxgroup.tax','order','warehouse','vendor','orderproduct', 'orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup','orderproduct.tax.taxgroup.tax','orderproduct.unit']);
        if($res){
            if(!empty($res->invoicedelivery)){
                foreach ($res->invoicedelivery as $item){
                    $item->delivery->total = $salesDeliveryRepo->getTotalDelivery($item->delivery->id);
                }
            }
            if($res->invoice_type == ProductType::SERVICE){
                $invProduct = SalesOrderProduct::where(array('order_id' => $res->order_id))->with(['tax','tax.taxgroup'])->get();
                $res->orderproductservice = $invProduct;
            }
            $res->payment_list = $this->invoiceRepo->getPaymentList($id);
            $getListDp = $this->invoiceRepo->getDpListBy($id);
            $res->has_dp = count($getListDp) > 0 ? true : false;
            $res->dp_id = count($getListDp) > 0 ? $getListDp[0]->id : "";
            $res->dp = count($getListDp) > 0 ? $getListDp : "";
           // $res->total_dp = count($getListDp) > 0 ? $getListDp[0]->downpayment->nominal : "";
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        }else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    public function kartuPiutang(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $vendorId = $request->vendor_id;
        $fromDate = !empty($request->from_date) ? $request->from_date : date('Y-m-d');
        $untilDate = !empty($request->until_date) ? $request->until_date : Utility::lastDateMonth();
        $vendorRepo = new VendorRepo(new Vendor());
        $where=array('vendor_type' => VendorType::CUSTOMER);
        if(!empty($vendorId)){
            $where[] = ['id','=',$vendorId];
        }
        $processedResults = collect();
        $findAllData =  $vendorRepo->getAllData($search,$where)->chunk(200, function ($input) use ($fromDate,$untilDate, &$processedResults) {
            $processedInvoice = $input->map(function ($vendor) use ($fromDate, $untilDate) {
                $saldoAwalInvoice = InvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate, "<");
                $saldoAwalPelunasan = PaymentInvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate, '<');
                $saldoAwal = $saldoAwalInvoice - $saldoAwalPelunasan;
                $invoice = InvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate);
                $pelunasan = PaymentInvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate);
                $saldoAkhir = ($invoice - $pelunasan) + $saldoAwal;
                $vendor->saldo_awal = $saldoAwal;
                $vendor->piutang = $invoice;
                $vendor->pelunasan = $pelunasan;
                $vendor->saldo_akhir = $saldoAkhir;
                return $vendor;
            })->filter(function ($vendor) {
                return $vendor->saldo_akhir > 0;
            });
            $processedResults = $processedResults->concat($processedInvoice);
        });
        $paginateVendor = $processedResults->forPage($page, $perpage)->values()->toArray();
        $totalRecords = $processedResults->count();
        if($findAllData)
        {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $paginateVendor;
            $this->data['has_more'] = false;
            $this->data['total'] = $totalRecords;
        } else{
            $this->data['status'] = false;
            $this->data['data'] = [];
            $this->data['has_more'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    public function showKartuPiutangDetail(Request $request)
    {
        $vendorId = $request->vendor_id;
        $page = $request->page;
        $perpage = $request->perpage;
        $fromDate = !empty($request->from_date) ? $request->from_date : date('Y-m-d');
        $untilDate = !empty($request->until_date) ? $request->until_date : Utility::lastDateMonth();
        $resultInvoice = SalesInvoicing::select('invoice_date as tanggal', 'invoice_no as nomor', 'note as note', DB::raw("'0' as kredit"), 'grandtotal as debet')->where([['vendor_id', '=', $vendorId]])->whereBetween('invoice_date',[$fromDate,$untilDate])->orderBy('invoice_date','asc');
        $resultPayment = SalesPaymentInvoice::select('payment_date as tanggal', 'payment_no as nomor', DB::raw("'Pelunasan' as note"), DB::raw('(total_payment + total_discount) - total_overpayment as kredit'), DB::raw("'0' as debet"))->where([['vendor_id', '=', $vendorId]])->whereBetween('payment_date',[$fromDate,$untilDate])->orderBy('payment_date','asc');
        $combinedResults = $resultInvoice->union($resultPayment);
        $paginator = $combinedResults->orderBy('tanggal', 'asc')->paginate($perpage, ['*'], 'page', $page);
        $totalCount = $paginator->total();
        $data = $paginator->items();
        if($data)
        {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['total'] = $totalCount;
        } else{
            $this->data['status'] = false;
            $this->data['data'] = [];
            $this->data['total'] = 0;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }


    public function destroy(Request $request)
    {
        $id = $request->id;
        DB::beginTransaction();
        try
        {
            $this->invoiceRepo->deleteAdditional($id);
            $this->invoiceRepo->delete($id);
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
                    $this->invoiceRepo->deleteAdditional($id);
                    $this->invoiceRepo->delete($id);
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

    public function getTotalInvoice(Request $request)
    {
        $filter = $request->input('filter');
        // $productType = $request->input('product_type'); // Use input() to get request data
        $query = SalesInvoicing::query(); // Use query() to build the query
        if (!empty($filter)) {
            if($filter == 'HARI_INI')
            {
                $query->whereDate('invoice_date', date('Y-m-d'));
            }
            else if($filter == 'BULAN_INI'){
                $query->whereMonth('invoice_date', date('Y-m-d'));
            }
            else if($filter == 'TAHUN_INI'){
                $query->whereYear('invoice_date', date('Y-m-d'));
            }
        }
        $totalInvoice = $query->sum('grandtotal'); // Execute the count query

        $response = [
            'status' => true,
            'message' => 'Data berhasil ditemukan',
            'data' => $totalInvoice
        ];

        return response()->json($response);
    }

    public function completion()
    {
        $query = SalesInvoicing::where('invoice_type', ProductType::ITEM);
        $completed = (clone $query)->where('invoice_status', StatusEnum::LUNAS)->count();
        $outstanding = (clone $query)->where('invoice_status', StatusEnum::BELUM_LUNAS)->count();
        $all = (clone $query)->count();
        $arrData = [
            ['name' => 'Completed', 'total' => $completed],
            ['name' => 'Outstanding', 'total' => $outstanding],
            ['name' => 'Total', 'total' => $all],
        ];
        $this->data['status'] = true;
        $this->data['message'] = 'Data berhasil ditemukan';
        $this->data['data'] = $arrData;
        return response()->json($this->data);
    }

    public function downloadSample(Request $request)
    {
        $orderType = $request->order_type;
        return Excel::download(new SampleSalesInvoiceExport($orderType), 'sample_invoice_penjualan.xlsx');
    }

    public function downloadSampleJurnal(Request $request)
    {
        return Excel::download(new SampleJurnalInvoiceExport(), 'sample_jurnal_invoice.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        $userId = $request->user_id;
        $orderType = $request->order_type;
        $import = new SalesInvoiceImport($userId,$orderType);
        Excel::import($import, $request->file('file'));

        if ($errors = $import->getErrors()) {
            return response()->json(['status' => false, 'success' => $import->getSuccessCount(),'messageError' => $errors,'errors' => count($errors), 'imported' => $import->getTotalRows()]);
        }

        return response()->json(['status' => false, 'success' => $import->getSuccessCount(),'messageError' => $errors,'errors' => count($errors), 'imported' => $import->getTotalRows()]);
    }

    public function importJurnal(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        $userId = $request->user_id;
        $import = new JurnalInvoiceImport($userId);
        Excel::import($import, $request->file('file'));

        if ($errors = $import->getErrors()) {
            return response()->json(['status' => false, 'success' => $import->getSuccessCount(),'messageError' => $errors,'errors' => count($errors), 'imported' => $import->getTotalRows()]);
        }

        return response()->json(['status' => true, 'success' => $import->getSuccessCount(),'messageError' => [],'errors' => 0, 'imported' => $import->getTotalRows()]);
    }

    private function prepareExportData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $total = $this->invoiceRepo->getAllTotalDataBy($search, $where);
        $data = $this->invoiceRepo->getAllDataBy($search, $page, $total, $where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request,'invoice-penjualan.xlsx');
    }

    public function exportCsv(Request $request){
        return $this->exportAsFormat($request,'invoice-penjualan.csv');
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
        $pdf = PDF::loadView('accounting::sales.sales_invoice_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('invoice-penjualan.pdf');
    }

    private function exportReportAsFormat(Request $request, string $filename,string $type = 'excel')
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->invoiceRepo->getAllDataBy($search, $page, $perpage, $where);
        if($type == 'excel'){
            return $this->downloadExcel($data, $params, $filename);
        } else {
            return $this->downloadPdf($request, $data, $params, $filename);
        }
    }

    private function downloadExcel($data, $params, $filename){
        return Excel::download(new SalesInvoiceReportExport($data,$params), $filename);
    }

    private function downloadPdf(Request $request, $data, $params, $filename){
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::sales.sales_invoice_detail_report', [
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
        return $this->exportReportAsFormat($request,'laporan-invoice-penjualan.xlsx');
    }

    public function exportReportCsv(Request $request){
        return $this->exportReportAsFormat($request,'laporan-invoice-penjualan.csv');
    }

    public function exportReportPdf(Request $request){
        return $this->exportReportAsFormat($request,'laporan-invoice-penjualan.pdf', 'pdf');
    }

    public function exportKartuPiutangExcel(Request $request)
    {
        $vendorId = $request->vendor_id;
        $fromDate = $request->from_date ?? date('Y-m-d');
        $untilDate = $request->until_date ?? date('Y-m-d');

        return Excel::download(
            new KartuPiutangExcelExport($vendorId, $fromDate, $untilDate),
            'kartu_piutang.xlsx'
        );
    }

    public function exportKartuPiutangSummaryPdf(Request $request)
    {
        $fromDate  = $request->from_date ?? date('Y-m-d');
        $untilDate = $request->until_date ?? Utility::lastDateMonth();
        $vendorId  = $request->vendor_id;

        // Jika vendorId terisi → ambil 1 vendor
        $vendors = $vendorId
            ? Vendor::where('id', $vendorId)->get()
            : Vendor::where('vendor_type', VendorType::CUSTOMER)->get();

        $result = [];

        foreach ($vendors as $vendor) {

            // Hitung saldo awal
            $saldoAwalInvoice   = InvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate, "<");
            $saldoAwalPelunasan = PaymentInvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate, "<");
            $saldoAwal          = $saldoAwalInvoice - $saldoAwalPelunasan;

            // Hitung pembelian & pelunasan periode berjalan
            $totalInvoice  = InvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate);
            $totalPayment  = PaymentInvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate);

            $saldoAkhir = ($saldoAwal + $totalInvoice) - $totalPayment;

            // Simpan ringkasan
            $result[] = [
                'vendor_name' => $vendor->vendor_name,
                'saldo_awal'  => $saldoAwal,
                'penjualan'   => $totalInvoice,
                'pelunasan'   => $totalPayment,
                'saldo_akhir' => $saldoAkhir,
            ];
        }

        $pdf = PDF::loadView('accounting::sales.kartu_piutang_summary', [
            'summary' => $result,
            'fromDate' => $fromDate,
            'untilDate' => $untilDate
        ])->setPaper('A4', 'portrait');

        return $pdf->download('kartu_piutang_rekap.pdf');
    }

    public function exportJurnal(Request $request)
    {
        return $this->exportJurnalAsFormat($request, 'jurnal-invoice-penjualan.xlsx');
    }

    public function exportJurnalCsv(Request $request)
    {
        return $this->exportJurnalAsFormat($request, 'jurnal-invoice-penjualan.csv');
    }

    private function exportJurnalAsFormat(Request $request, string $filename)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $where[] = ['method' => 'where', 'value' => [['input_type', '=', InputType::SALDO_AWAL]]];
        $total = $this->invoiceRepo->getAllTotalDataBy($search, $where);
        $data = $this->invoiceRepo->getAllDataBy($search, $page, $total, $where);
        return Excel::download(new JurnalInvoiceExport($data), $filename);
    }

    public function exportJurnalPdf(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $where[] = ['method' => 'where', 'value' => [['input_type', '=', InputType::SALDO_AWAL]]];
        $total = $this->invoiceRepo->getAllTotalDataBy($search, $where);
        $data = $this->invoiceRepo->getAllDataBy($search, $page, $total, $where);
        $export = new JurnalInvoiceExport($data);
        $pdf = PDF::loadView('accounting::sales.jurnal_invoice_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('jurnal-invoice-penjualan.pdf');
    }

}
