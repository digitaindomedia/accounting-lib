<?php

namespace Icso\Accounting\Http\Controllers\Pembelian;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Exports\PurchaseOrderExport;
use Icso\Accounting\Exports\PurchaseOrderReportDetailExport;
use Icso\Accounting\Exports\SamplePurchaseOrderExport;
use Icso\Accounting\Http\Requests\CreatePurchaseOrderRequest;
use Icso\Accounting\Imports\PurchaseOrderImport;
use Icso\Accounting\Models\Pembelian\Order\PurchaseOrder;
use Icso\Accounting\Models\Pembelian\UangMuka\PurchaseDownPayment;
use Icso\Accounting\Repositories\Pembelian\Downpayment\DpRepo;
use Icso\Accounting\Repositories\Pembelian\Order\OrderRepo;
use Icso\Accounting\Repositories\Pembelian\Received\ReceiveRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\ProductType;
use Icso\Accounting\Utils\TransactionsCode;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class OrderController extends Controller
{
    protected $purchaseOrderRepo;
    protected $purchaseReceivedRepo;
    protected $purchaseDpRepo;

    public function __construct(OrderRepo $purchaseOrderRepo, ReceiveRepo $purchaseReceivedRepo, DpRepo $purchaseDpRepo)
    {
        $this->purchaseOrderRepo = $purchaseOrderRepo;
        $this->purchaseReceivedRepo = $purchaseReceivedRepo;
        $this->purchaseDpRepo = $purchaseDpRepo;
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
        return compact('search', 'page', 'perpage', 'orderType','from','fromDate','untilDate','vendorId');
    }

    public function getAllData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $whereBetween = $this->buildWhereBetweenClause($fromDate, $untilDate);
        $where = $this->buildWhereClause($vendorId, $orderType, $from);

        $data = $this->purchaseOrderRepo->getAllDataBetweenBy($search, $page, $perpage, $where, $whereBetween);
        $total = $this->purchaseOrderRepo->getAllTotalDataBetweenBy($search, $where, $whereBetween);

        $hasMore = Helpers::hasMoreData($total, $page, $data);

        if (count($data) > 0) {
            $data = $this->processOrderData($data, $orderType, $from);
            $this->data = [
                'status' => true,
                'message' => 'Data berhasil ditemukan',
                'data' => $data,
                'has_more' => $hasMore,
                'total' => $total,
            ];
        } else {
            $this->data = [
                'status' => false,
                'message' => 'Data tidak ditemukan',
                'data' => [],
            ];
        }

        return response()->json($this->data);
    }

    protected function buildWhereBetweenClause($fromDate, $untilDate)
    {
        return (!empty($fromDate) && !empty($untilDate)) ? [$fromDate, $untilDate] : [];
    }

    protected function buildWhereClause($vendorId, $orderType, $from)
    {
        $where = [];

        if (!empty($vendorId)) {
            $where[] = ['method' => 'where', 'value' => [['vendor_id', '=', $vendorId]]];
        }

        if (!empty($orderType)) {
            $where[] = ['method' => 'where', 'value' => [['order_type', '=', $orderType]]];
        }

        if (!empty($from)) {
            switch ($from) {
                case TransactionsCode::PENERIMAAN:
                    $where[] = ['method' => 'where', 'value' => [['order_status', '=', StatusEnum::PARSIAL_PENERIMAAN]]];
                    $where[] = ['method' => 'orWhere', 'value' => [['order_status', '=', StatusEnum::OPEN]]];
                    break;
                case TransactionsCode::UANG_MUKA_PEMBELIAN:
                    $where[] = ['method' => 'where', 'value' => [['order_status', '!=', StatusEnum::INVOICE]]];
                    break;
                case TransactionsCode::INVOICE_PEMBELIAN:
                    $where[] = ['method' => 'where', 'value' => [['order_status', '=', StatusEnum::PARSIAL_PENERIMAAN]]];
                    $where[] = ['method' => 'orWhere', 'value' => [['order_status', '=', StatusEnum::PENERIMAAN]]];
                    break;
            }
        }

        return $where;
    }

    protected function processOrderData($data, $orderType, $from)
    {
        foreach ($data as $item) {
            $item->total_dp = $this->purchaseDpRepo->getTotalUangMukaByOrderId($item->id);

            if ($orderType == ProductType::ITEM) {
                $item->available_received = $this->purchaseOrderRepo->findInUseInPenerimaanById($item->id)['order_product'];

                if ($from == TransactionsCode::INVOICE_PEMBELIAN) {
                    $item->received = $this->processInvoicePembelianData($item->id);
                    $item->has_dp = $this->checkForOpenDownPayments($item->id);
                }
            } else {
                $item->available_received = $this->purchaseOrderRepo->findInUseInBastById($item->id)['order_service'];
            }
        }

        return $data;
    }

    protected function processInvoicePembelianData($orderId)
    {
        $findPenerimaan = $this->purchaseReceivedRepo->findAllByWhere(
            ['order_id' => $orderId, 'receive_status' => StatusEnum::OPEN],
            [],
            ['vendor', 'order', 'warehouse', 'receiveproduct', 'receiveproduct.unit', 'receiveproduct.product', 'receiveproduct.tax', 'receiveproduct.tax.taxgroup', 'receiveproduct.tax.taxgroup.tax']
        );

        if (count($findPenerimaan) > 0) {
            foreach ($findPenerimaan as $value) {
                $value->total = $this->purchaseReceivedRepo->getTotalReceived($value->id);
            }
        }

        return $findPenerimaan;
    }

    protected function checkForOpenDownPayments($orderId)
    {
        $countDP = PurchaseDownPayment::where(['order_id' => $orderId, 'downpayment_status' => StatusEnum::OPEN])->count();
        return $countDP > 0;
    }

    public function store(CreatePurchaseOrderRequest $request){
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
        $res = $this->purchaseOrderRepo->findOne($request->id,array(),['orderproduct','coa', 'ordermeta', 'vendor', 'orderproduct.product','orderproduct.tax','orderproduct.tax.taxgroup','orderproduct.tax.taxgroup.tax','orderproduct.unit','purchaserequest']);
        if($res){
            $res->transactions = $this->purchaseOrderRepo->getTransaksi($request->id);
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

    public function showNotReceived(Request $request){
        $id = $request->id;
        $res = $this->purchaseOrderRepo->findInUseInPenerimaanById($id);
        if($res) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        } else{
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal ditemukan";
            $this->data['data'] = '';
        }
        return response()->json($this->data);
    }

    public function delete(Request $request){
        $countDp = PurchaseDownPayment::where('order_id', $request->id)->count();
        if($countDp > 0){
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal dihapus, sudah ada uang muka";
            return response()->json($this->data);
        }

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
                $countDp = PurchaseDownPayment::where('order_id', $id)->count();
                if($countDp > 0){
                    $failedDelete = $failedDelete + 1;
                    continue;
                }

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

    public function completion()
    {
        $query = PurchaseOrder::where('order_type', ProductType::ITEM);
        $completed = (clone $query)->whereIn('order_status',[StatusEnum::SELESAI, StatusEnum::PENERIMAAN, StatusEnum::INVOICE])->count();
        $outstanding = (clone $query)->whereIn('order_status',[StatusEnum::OPEN, StatusEnum::PARSIAL_PENERIMAAN])->count();
        $all = $query->count();
        $arrData = [
            ['name' => 'Completed (Sudah Penerimaan)', 'total' => $completed],
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
        return Excel::download(new SamplePurchaseOrderExport($orderType), 'sample_order_pembelian.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        $userId = $request->user_id;
        $orderType = $request->order_type;
        $import = new PurchaseOrderImport($userId,$orderType);
        Excel::import($import, $request->file('file'));

        if ($errors = $import->getErrors()) {
            return response()->json(['status' => false, 'success' => $import->getSuccessCount(),'messageError' => $errors,'errors' => count($errors), 'imported' => $import->getTotalRows()]);
        }

        return response()->json(['status' => true,'success' => $import->getSuccessCount(),'errors' => count($import->getErrors()), 'message' => 'File berhasil import', 'imported' => $import->getTotalRows()], 200);
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request,'order-pembelian.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request,'order-pembelian.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $whereBetween = $this->buildWhereBetweenClause($fromDate, $untilDate);
        $where = $this->buildWhereClause($vendorId, $orderType, $from);
        $total = $this->purchaseOrderRepo->getAllTotalDataBetweenBy($search, $where, $whereBetween);
        $data = $this->purchaseOrderRepo->getAllDataBetweenBy($search, $page, $total, $where, $whereBetween);
        return Excel::download(new PurchaseOrderExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $whereBetween = $this->buildWhereBetweenClause($fromDate, $untilDate);
        $where = $this->buildWhereClause($vendorId, $orderType, $from);
        $total = $this->purchaseOrderRepo->getAllTotalDataBetweenBy($search, $where, $whereBetween);
        $data = $this->purchaseOrderRepo->getAllDataBetweenBy($search, $page, $total, $where, $whereBetween);
        $export = new PurchaseOrderExport($data);
        $pdf = PDF::loadView('accounting::purchase.purchase_order_pdf', ['arrData' => $export->collection()]);

        return $pdf->download('order-pembelian.pdf');
    }

    public function exportReportExcel(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $whereBetween = $this->buildWhereBetweenClause($fromDate, $untilDate);
        $where = $this->buildWhereClause($vendorId, $orderType, $from);
        $total = $this->purchaseOrderRepo->getAllTotalDataBetweenBy($search, $where, $whereBetween);
        $data = $this->purchaseOrderRepo->getAllDataBetweenBy($search, $page, $total, $where, $whereBetween);
        return Excel::download(new PurchaseOrderReportDetailExport($data,$params), 'excel-purchase-order.xlsx');
    }

    public function exportReportPdf(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $whereBetween = $this->buildWhereBetweenClause($fromDate, $untilDate);
        $where = $this->buildWhereClause($vendorId, $orderType, $from);
        $total = $this->purchaseOrderRepo->getAllTotalDataBetweenBy($search, $where, $whereBetween);
        $data = $this->purchaseOrderRepo->getAllDataBetweenBy($search, $page, $total, $where, $whereBetween);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::purchase.purchase_order_detail_report', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream('laporan-order-pembelian.pdf');
        }

        return $pdf->download('laporan-order-pembelian.pdf');
    }
}
