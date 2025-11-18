<?php

namespace Icso\Accounting\Http\Controllers\Pembelian;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Exports\KartuHutangExcelExport;
use Icso\Accounting\Exports\PurchaseInvoiceExport;
use Icso\Accounting\Exports\PurchaseInvoiceReportDetailExport;
use Icso\Accounting\Exports\SamplePurchaseInvoiceExport;
use Icso\Accounting\Http\Requests\CreatePurchaseInvoiceRequest;
use Icso\Accounting\Imports\PurchaseInvoiceImport;
use Icso\Accounting\Models\Master\Vendor;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicing;
use Icso\Accounting\Models\Pembelian\Invoicing\PurchaseInvoicingFakturPajak;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrderProduct;
use Icso\Accounting\Models\Pembelian\Pembayaran\PurchasePaymentInvoice;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Icso\Accounting\Repositories\Master\Vendor\VendorRepo;
use Icso\Accounting\Repositories\Pembelian\Invoice\InvoiceRepo;
use Icso\Accounting\Repositories\Pembelian\Payment\PaymentInvoiceRepo;
use Icso\Accounting\Repositories\Pembelian\Received\ReceiveRepo;
use Icso\Accounting\Repositories\Pembelian\Retur\ReturRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\InputType;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\Utility;
use Icso\Accounting\Utils\VendorType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class InvoiceController extends Controller
{
    protected $invoiceRepo;
    protected $paymentInvoiceRepo;
    protected $returRepo;

    public function __construct(InvoiceRepo $invoiceRepo, PaymentInvoiceRepo $paymentInvoiceRepo,ReturRepo $returRepo)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentInvoiceRepo = $paymentInvoiceRepo;
        $this->returRepo = $returRepo;
    }

    public function getAllData(Request $request){
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->invoiceRepo->getAllDataBy($search, $page, $perpage,$where);
        $total = $this->invoiceRepo->getAllTotalDataBy($search, $where);
        $hasMore = Helpers::hasMoreData($total,$page,$data);
        $purchaseReceivedRepo = new ReceiveRepo(new PurchaseReceived());
        if(count($data) > 0) {
            foreach ($data as $item) {
                if(!empty($item->invoicereceived)){
                    foreach ($item->invoicereceived as $itemRec){
                        $itemRec->receive->total = $purchaseReceivedRepo->getTotalReceived($itemRec->receive->id);
                    }
                }
                if($item->invoice_type == ProductType::SERVICE){
                    $invProduct = PurchaseOrderProduct::where(array('order_id' => $item->order_id))->with(['tax','tax.taxgroup'])->get();
                    $item->orderproductservice = $invProduct;
                }
                $paid = $this->paymentInvoiceRepo->getAllPaymentByInvoiceId($item->id);
                $left_bill = $item->grandtotal - $paid;
                $item->left_bill = $left_bill;
                $item->nominal_paid = $left_bill;
                $item->paid = $paid;
                $getListDp = $this->invoiceRepo->getDpListBy($item->id);
                $item->has_dp = count($getListDp) > 0 ? true : false;
                $item->dp_id = count($getListDp) > 0 ? $getListDp[0]->id : "";
                $item->dp = count($getListDp) > 0 ? $getListDp : "";
            }
            if(!empty($vendorId)){
                $findRetur = $this->returRepo->findAllByWhere(array('retur_status' => StatusEnum::OPEN,'vendor_id' => $vendorId));
                $this->data['retur'] = $findRetur;
            }

            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['has_more'] = $hasMore;
            $this->data['total'] = $total;
            $this->data['key'] = !empty($status) ? $status : "all";
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
            $this->data['data'] = [];
            $this->data['key'] =!empty($status) ? $status : "all";
        }
        return response()->json($this->data);
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $inputType = $request->input_type;
        $invoiceType = $request->invoice_type;
        $coaId = $request->coa_id;
        $vendorId = $request->vendor_id;
        $status = $request->status;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $orderId = $request->order_id;

        $where=array();
        if(!empty($inputType)){
            $where[] = array(
                'method' => 'where',
                'value' => [['input_type','=',$inputType]]);
        }
        if(!empty($invoiceType)){
            $where[] = array(
                'method' => 'where',
                'value' => [['invoice_type','=',$invoiceType]]);
        }
        if(!empty($coaId)){
            $where[] = array(
                'method' => 'where',
                'value' => [['coa_id','=',$coaId]]);
        }
        if(!empty($vendorId)){
            $where[] = array(
                'method' => 'where',
                'value' => [['vendor_id','=',$vendorId]]);
        }
        if(!empty($status)){
            $where[] = array(
                'method' => 'where',
                'value' => [['invoice_status','=',$status]]);

        }
        if(isset($orderId) && ($orderId === '0' || !empty($orderId)))
        {
            $where[] = array(
                'method' => 'where',
                'value' => [['order_id','=',$orderId]]);
        }
        if(!empty($fromDate) && !empty($untilDate)){
            $where[] = array(
                'method' => 'whereBetween',
                'value' => array('field' => 'invoice_date', 'value' => [$fromDate,$untilDate]));
        }
        return compact('search', 'page', 'perpage', 'where','fromDate','untilDate');
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

    public function store(CreatePurchaseInvoiceRequest $request){
        $res = $this->invoiceRepo->store($request);
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
        $purchaseReceivedRepo = new ReceiveRepo(new PurchaseReceived());
        $id = $request->id;
        $res = $this->invoiceRepo->findOne($id,array(),['vendor','warehouse','invoicereceived.receive.order','invoicereceived','invoicereceived.receive.warehouse','invoicereceived.receive.receiveproduct','invoicereceived.receive.receiveproduct.unit','invoicereceived.receive.receiveproduct.product','invoicereceived.receive.receiveproduct.tax','invoicereceived.receive.receiveproduct.tax.taxgroup','invoicereceived.receive.receiveproduct.tax.taxgroup.tax','order','warehouse','vendor','orderproduct', 'orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup','orderproduct.tax.taxgroup.tax','orderproduct.unit']);
        if($res){
            if(!empty($res->invoicereceived)){
                foreach ($res->invoicereceived as $item){
                    $item->receive->total = $purchaseReceivedRepo->getTotalReceived($item->receive->id);
                }
            }
            if($res->invoice_type == ProductType::SERVICE){
                $invProduct = PurchaseOrderProduct::where(array('order_id' => $res->order_id))->with(['tax','tax.taxgroup'])->get();
                $res->orderproductservice = $invProduct;
            }
            $res->payment_list = $this->invoiceRepo->getPaymentList($id);
            $getListDp = $this->invoiceRepo->getDpListBy($id);
            $res->has_dp = count($getListDp) > 0 ? true : false;
            $res->dp_id = count($getListDp) > 0 ? $getListDp[0]->id : "";
            $res->dp = count($getListDp) > 0 ? $getListDp : "";
            //$res->total_dp = count($getListDp) > 0 ? $getListDp[0]- : "";
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        }else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    public function getAllFakturPajak(Request $request)
    {
        $res = PurchaseInvoicingFakturPajak::where(array('invoice_id' => $request->invoice_id))->with(['invoice'])->get();
        if($res){
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal ditemukan";
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }

    public function saveFakturPajak(Request $request)
    {
        DB::beginTransaction();
        try{
            PurchaseInvoicingFakturPajak::where(array('invoice_id' => $request->invoice_id))->delete();
            $faktur = json_decode(json_encode($request->faktur_pajak));
            if(!empty($faktur)){
                foreach ($faktur as $item){
                    $arrData = array(
                        'invoice_id' => $request->invoice_id,
                        'faktur_date' => Utility::changeDateFormat($item->faktur_date),
                        'faktur_no' => $item->faktur_no,
                        'faktur_nominal' => Utility::remove_commas($item->faktur_nominal)
                    );
                    PurchaseInvoicingFakturPajak::create($arrData);
                }
            }

            DB::commit();
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';
            $this->data['data'] = '';
        }
        catch (\Exception $e) {
            echo $e->getMessage();
            DB::rollBack();
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal disimpan';
            $this->data['data'] = '';
        }
        return response()->json($this->data);
    }

    public function kartuHutang(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $vendorId = $request->vendor_id;
        $fromDate = !empty($request->from_date) ? $request->from_date : date('Y-m-d');
        $untilDate = !empty($request->until_date) ? $request->until_date : Utility::lastDateMonth();

        $vendorRepo = new VendorRepo(new Vendor());
        $where=array('vendor_type' => VendorType::SUPPLIER);
        if(!empty($vendorId)){
            $where[] = ['id','=',$vendorId];
        }

        /*$findAllData = $vendorRepo->getAllData($search,$where);
        $vendorPurchaseDetail = $findAllData->map(function ($vendor) use($fromDate,$untilDate){
            $saldoAwalInvoice = InvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate, "<");
            $saldoAwalPelunasan = PaymentInvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate, '<');
            $saldoAwal = $saldoAwalInvoice - $saldoAwalPelunasan;
            $invoice = InvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate);
            $pelunasan = PaymentInvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate);
            $saldoAkhir = ($invoice - $pelunasan) + $saldoAwal;
            $vendor->saldo_awal = $saldoAwal;
            $vendor->hutang = $invoice;
            $vendor->pelunasan = $pelunasan;
            $vendor->saldo_akhir = $saldoAkhir;
            return $vendor;
        })->filter(function ($vendor) {
            // Filter out models where the totalAmountBetweenDates is 0
            if($vendor->saldo_akhir > 0)
            {
                return $vendor;
            }
        });*/
        $processedResults = collect();
        $findAllData =  $vendorRepo->getAllData($search,$where)->chunk(200, function ($input) use ($fromDate,$untilDate, &$processedResults) {
            $processedInvoice = $input->map(function ($vendor) use ($fromDate,$untilDate) {
                $saldoAwalInvoice = InvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate, "<");
                $saldoAwalPelunasan = PaymentInvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate, '<');
                $saldoAwal = $saldoAwalInvoice - $saldoAwalPelunasan;
                $invoice = InvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate);
                $pelunasan = PaymentInvoiceRepo::sumGrandTotalByVendor($vendor->id, $fromDate, $untilDate);
                $saldoAkhir = ($invoice - $pelunasan) + $saldoAwal;
                $vendor->saldo_awal = $saldoAwal;
                $vendor->hutang = $invoice;
                $vendor->pelunasan = $pelunasan;
                $vendor->saldo_akhir = $saldoAkhir;
                return $vendor;
            })->filter(function ($vendor) {
                // Filter out records with totalAmountBetweenDates equal to 0
                return $vendor->saldo_akhir > 0;
            });

            // Concatenate the processed results to the container
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

    public function showKartuHutangDetail(Request $request)
    {
        $vendorId = $request->vendor_id;
        $page = $request->page;
        $perpage = $request->perpage;
        $fromDate = !empty($request->from_date) ? $request->from_date : date('Y-m-d');
        $untilDate = !empty($request->until_date) ? $request->until_date : Utility::lastDateMonth();
        $resultInvoice = PurchaseInvoicing::select('invoice_date as tanggal', 'invoice_no as nomor', 'note as note', DB::raw("'0' as debet"), 'grandtotal as kredit')->where([['vendor_id', '=', $vendorId]])->whereBetween('invoice_date',[$fromDate,$untilDate])->orderBy('invoice_date','asc');
        $resultPayment = PurchasePaymentInvoice::select('payment_date as tanggal', 'payment_no as nomor', DB::raw("'Pelunasan' as note"), DB::raw('(total_payment + total_discount) - total_overpayment as debet'), DB::raw("'0' as kredit"))->where([['vendor_id', '=', $vendorId]])->whereBetween('payment_date',[$fromDate,$untilDate])->orderBy('payment_date','asc');
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
        $query = PurchaseInvoicing::query(); // Use query() to build the query
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
        $query = PurchaseInvoicing::where('invoice_type', ProductType::ITEM);
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
        return Excel::download(new SamplePurchaseInvoiceExport($orderType), 'sample_invoice_pembelian.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        $userId = $request->user_id;
        $orderType = $request->order_type;
        $import = new PurchaseInvoiceImport($userId,$orderType);
        Excel::import($import, $request->file('file'));

        if ($errors = $import->getErrors()) {
            return response()->json(['status' => false, 'success' => $import->getSuccessCount(),'messageError' => $errors,'errors' => count($errors), 'imported' => $import->getTotalRows()]);
        }

        return response()->json(['status' => true,'success' => $import->getSuccessCount(),'errors' => count($import->getErrors()), 'message' => 'File berhasil import', 'imported' => $import->getTotalRows()], 200);
    }

    private function prepareExportData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $total = $this->invoiceRepo->getAllTotalDataBy($search, $where);
        $data = $this->invoiceRepo->getAllDataBy($search, $page, $total,$where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request,'invoice-pembelian.xlsx');
    }


    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request,'invoice-pembelian.csv');
    }


    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new PurchaseInvoiceExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new PurchaseInvoiceExport($data);
        $pdf = PDF::loadView('accounting::purchase.purchase_invoice_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('invoice-pembelian.pdf');
    }

    public function exportReportExcel(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->invoiceRepo->getAllDataBy($search, $page, $perpage, $where);
        return Excel::download(new PurchaseInvoiceReportDetailExport($data,$params), 'excel-purchase-invoice.xlsx');

    }

    public function exportReportPdf(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->invoiceRepo->getAllDataBy($search, $page, $perpage, $where);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::purchase.purchase_invoice_detail_report', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream('laporan-invoice-pembelian.pdf');
        }

        return $pdf->download('laporan-invoice-pembelian.pdf');

    }

    public function exportKartuHutangExcel(Request $request)
    {
        $vendorId = $request->vendor_id;
        $fromDate = $request->from_date ?? date('Y-m-d');
        $untilDate = $request->until_date ?? date('Y-m-d');

        return Excel::download(
            new KartuHutangExcelExport($vendorId, $fromDate, $untilDate),
            'kartu_hutang.xlsx'
        );
    }

    public function exportKartuHutangSummaryPdf(Request $request)
    {
        $fromDate  = $request->from_date ?? date('Y-m-d');
        $untilDate = $request->until_date ?? \Utility::lastDateMonth();
        $vendorId  = $request->vendor_id;

        // Jika vendorId terisi → ambil 1 vendor
        $vendors = $vendorId
            ? Vendor::where('id', $vendorId)->get()
            : Vendor::where('vendor_type', 'supplier')->get();

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
                'pembelian'   => $totalInvoice,
                'pelunasan'   => $totalPayment,
                'saldo_akhir' => $saldoAkhir,
            ];
        }

        $pdf = PDF::loadView('accounting::purchase.kartu_hutang_summary_pdf', [
            'summary' => $result,
            'fromDate' => $fromDate,
            'untilDate' => $untilDate
        ])->setPaper('A4', 'portrait');

        return $pdf->download('kartu_hutang_rekap.pdf');
    }

}
