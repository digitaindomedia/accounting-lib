<?php

namespace Icso\Accounting\Http\Controllers\Penjualan;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Exports\SalesOrderExport;
use Icso\Accounting\Exports\SalesOrderReportDetail;
use Icso\Accounting\Exports\SampleSalesOrderExport;
use Icso\Accounting\Http\Requests\CreateSalesOrderRequest;
use Icso\Accounting\Imports\SalesOrderImport;
use Icso\Accounting\Models\Penjualan\Order\SalesOrder;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Icso\Accounting\Repositories\Penjualan\Delivery\DeliveryRepo;
use Icso\Accounting\Repositories\Penjualan\Downpayment\DpRepo;
use Icso\Accounting\Repositories\Penjualan\Order\SalesOrderRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\TransactionsCode;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;

class OrderController extends Controller
{
    protected $salesOrderRepo;
    protected $salesDpRepo;
    protected $deliveryRepo;
    protected $data = [];

    public function __construct(SalesOrderRepo $salesOrderRepo, DpRepo $salesDpRepo, DeliveryRepo $deliveryRepo)
    {
        $this->salesOrderRepo = $salesOrderRepo;
        $this->salesDpRepo = $salesDpRepo;
        $this->deliveryRepo = $deliveryRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $vendorId = $request->vendor_id;
        $from = $request->from;
        $orderType = $request->order_type;
        $where = $this->buildWhereClause($vendorId, $orderType, $from, $fromDate, $untilDate);
        return compact('search', 'page', 'perpage', 'where', 'orderType','from','fromDate','untilDate');
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->salesOrderRepo->getAllDataBy($search, $page, $perpage, $where);
        $total = $this->salesOrderRepo->getAllTotalDataBy($search, $where);

        $hasMore = Helpers::hasMoreData($total, $page, $data);
        if (count($data) > 0) {
            $data = $this->processDeliveryData($data, $orderType, $from);
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['has_more'] = $hasMore;
            $this->data['total'] = $total;
        } else {
            $this->data['status'] = false;
            $this->data['data'] = array();
            $this->data['message'] = 'Data tidak ditemukan';
        }
        return response()->json($this->data);
    }

    protected function buildWhereClause($vendorId, $orderType, $from, $fromDate, $untilDate)
    {
        $where = array();
        if (!empty($vendorId)) {
            $where[] = ['method' => 'where', 'value' => [['vendor_id', '=', $vendorId]]];
        }
        if (!empty($orderType)) {
            $where[] = ['method' => 'where', 'value' => [['order_type', '=', $orderType]]];
        }
        if (!empty($from)) {
            if ($from == TransactionsCode::DELIVERY_ORDER) {
                $where[] = ['method' => 'where', 'value' => [['order_status', '=', StatusEnum::PARSIAL_DELIVERY]]];
                $where[] = ['method' => 'orWhere', 'value' => [['order_status', '=', StatusEnum::OPEN]]];
            } else if ($from == TransactionsCode::UANG_MUKA_PENJUALAN) {
                $where[] = ['method' => 'where', 'value' => [['order_status', '!=', StatusEnum::INVOICE]]];
            } else if ($from == TransactionsCode::INVOICE_PENJUALAN) {
                $where[] = ['method' => 'where', 'value' => [['order_status', '=', StatusEnum::PARSIAL_DELIVERY]]];
                $where[] = ['method' => 'orWhere', 'value' => [['order_status', '=', StatusEnum::DELIVERY]]];
            }
        }
        if (!empty($fromDate) && !empty($untilDate)) {
            $where[] = ['method' => 'whereBetween', 'value' => ['field' => 'order_date', 'value' => [$fromDate, $untilDate]]];
        }
        return $where;
    }

    protected function processDeliveryData($data, $orderType, $from)
    {
        if ($orderType == ProductType::ITEM && $from == TransactionsCode::INVOICE_PENJUALAN) {
            foreach ($data as $item) {
                $findDelivery = SalesDelivery::where(['order_id' => $item->id, 'delivery_status' => StatusEnum::OPEN])
                    ->with(['vendor', 'order', 'warehouse', 'deliveryproduct', 'deliveryproduct.unit', 'deliveryproduct.product', 'deliveryproduct.tax', 'deliveryproduct.tax.taxgroup', 'deliveryproduct.tax.taxgroup.tax'])
                    ->get();
                if (count($findDelivery) > 0) {
                    foreach ($findDelivery as $value) {
                        $value->total = $this->deliveryRepo->getTotalDelivery($value->id);
                    }
                }
                $item->delivery = $findDelivery;
            }
        }
        return $data;
    }

    public function store(CreateSalesOrderRequest $request){
        $res = $this->salesOrderRepo->store($request);
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
        $res = $this->salesOrderRepo->findOne($request->id,array(),['orderproduct','salesquotation','ordermeta', 'vendor', 'orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup','orderproduct.tax.taxgroup.tax','orderproduct.unit']);
        if($res){
           // $res->transactions = $this->salesOrderRepo->getTransaksi($request->id);
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
        $checkDp = $this->salesDpRepo->countByOrderId($request->id);
        if($checkDp > 0){
            $this->data['status'] = false;
            $this->data['message'] = "Data tidak bisa dihapus karena sudah ada uang muka";
            return response()->json($this->data);
        }
        $res = $this->salesOrderRepo->delete($request->id);
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
                $checkDp = $this->salesDpRepo->countByOrderId($id);
                if($checkDp > 0){
                    $failedDelete = $failedDelete + 1;
                    continue;
                }
                $res = $this->salesOrderRepo->delete($id);
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

    public function completion()
    {
        $query = SalesOrder::where('order_type', ProductType::ITEM);
        $completed = (clone $query)->whereIn('order_status',[StatusEnum::SELESAI, StatusEnum::DELIVERY, StatusEnum::INVOICE])->count();
        $outstanding = (clone $query)->whereIn('order_status',[StatusEnum::OPEN, StatusEnum::PARSIAL_DELIVERY])->count();
        $all = $query->count();
        $arrData = [
            ['name' => 'Completed (Sudah Pengiriman)', 'total' => $completed],
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
        return Excel::download(new SampleSalesOrderExport($orderType), 'sample_order_penjualan.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        $userId = $request->user_id;
        $orderType = $request->order_type;
        $import = new SalesOrderImport($userId,$orderType);
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
        $total = $this->salesOrderRepo->getAllTotalDataBy($search, $where);
        $data = $this->salesOrderRepo->getAllDataBy($search, $page, $total, $where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request,'order-penjualan.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request,'order-penjualan.csv');
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new SalesOrderExport($data);
        $pdf = Pdf::loadView('accounting::sales.sales_order_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('order-penjualan.pdf');
    }

    public function exportReportExcel(Request $request)
    {
        return $this->exportReportAsFormat($request,'laporan-order-penjualan.xlsx');
    }

    public function exportReportCsv(Request $request){
        return $this->exportReportAsFormat($request,'laporan-order-penjualan.csv');
    }

    public function exportReportPdf(Request $request){
        return $this->exportReportAsFormat($request,'laporan-order-penjualan.pdf', 'pdf');
    }

    private function exportReportAsFormat(Request $request, string $filename,string $type = 'excel')
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->salesOrderRepo->getAllDataBy($search, $page, $perpage, $where);
        if($type == 'excel'){
            return $this->downloadExcel($data, $params, $filename);
        } else {
            return $this->downloadPdf($request, $data, $params, $filename);
        }
    }

    private function downloadExcel($data, $params, $filename){
        return Excel::download(new SalesOrderReportDetail($data,$params), $filename);
    }

    private function downloadPdf(Request $request, $data, $params, $filename){
        $pdf = Pdf::loadView('accounting::sales.sales_order_report_detail', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new SalesOrderExport($data), $filename);
    }

}
