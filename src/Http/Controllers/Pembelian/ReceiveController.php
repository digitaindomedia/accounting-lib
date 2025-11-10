<?php

namespace Icso\Accounting\Http\Controllers\Pembelian;

use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Exports\PurchaseReceivedExport;
use Icso\Accounting\Exports\PurchaseReceivedReportDetailExport;
use Icso\Accounting\Http\Requests\CreatePurchaseReceivedRequest;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Icso\Accounting\Repositories\Pembelian\Received\ReceiveRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\ProductType;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReceiveController extends Controller
{
    protected $purchaseReceivedRepo;

    public function __construct(ReceiveRepo $purchaseReceivedRepo)
    {
        $this->purchaseReceivedRepo = $purchaseReceivedRepo;
    }

    public function getAllData(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->purchaseReceivedRepo->getAllDataBetweenBy($search,$page,$perpage,$where,$whereBetween);
        $total = $this->purchaseReceivedRepo->getAllTotalDataBetweenBy($search, $where,$whereBetween);
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
        }
        return response()->json($this->data);
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $vendorId = $request->vendor_id;
        $warehouseId = $request->warehouse_id;

        $whereBetween = array();
        if(!empty($fromDate) && !empty($untilDate)){
            $whereBetween = [$fromDate,$untilDate];
        }
        $where = array();
        if(!empty($vendorId)){
            $where[] = ['vendor_id', '=', $vendorId];
        }
        if(!empty($warehouseId)){
            $where[] = ['warehouse_id', '=', $warehouseId];
        }
        return compact('search', 'page', 'perpage', 'whereBetween', 'where','fromDate','untilDate');
    }

    public function store(CreatePurchaseReceivedRequest $request): \Illuminate\Http\JsonResponse
    {
        $res = $this->purchaseReceivedRepo->store($request);
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
        $res = $this->purchaseReceivedRepo->findOne($request->id,array(),['vendor', 'order', 'warehouse','receiveproduct', 'receiveproduct.product','receiveproduct.orderproduct','receiveproduct.tax','receiveproduct.unit']);
        if($res){
            if(!empty($res->receiveproduct))
            {
                foreach ($res->receiveproduct as $value){
                    $value->qty_received =ReceiveRepo::getReceivedProduct($value->product_id,$value->receive_id,$value->unit_id);
                }
            }
            $res->transactions = $this->purchaseReceivedRepo->getTransaksi($request->id);
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

    public function destroy(Request $request)
    {
        $id = $request->id;
        DB::beginTransaction();
        try
        {
            $this->purchaseReceivedRepo->deleteAdditional($id);
            $this->purchaseReceivedRepo->delete($id);
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
                    $this->purchaseReceivedRepo->deleteAdditional($id);
                    $this->purchaseReceivedRepo->delete($id);
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

    public function completion()
    {
        $completed = PurchaseReceived::whereHas('order', function ($query) {
            $query->where('order_type', ProductType::ITEM)->whereIn('order_status',[StatusEnum::INVOICE]);
        })->count();
        $outstanding = PurchaseReceived::whereHas('order', function ($query) {
            $query->where('order_type', ProductType::ITEM)->whereIn('order_status',[StatusEnum::PENERIMAAN, StatusEnum::PARSIAL_PENERIMAAN]);
        })->count();
        $all = PurchaseReceived::count();
        $arrData = [
            ['name' => 'Completed (Sudah Invoice)', 'total' => $completed],
            ['name' => 'Outstanding', 'total' => $outstanding],
            ['name' => 'Total', 'total' => $all],
        ];
        $this->data['status'] = true;
        $this->data['message'] = 'Data berhasil ditemukan';
        $this->data['data'] = $arrData;
        return response()->json($this->data);
    }

    private function prepareExportData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $total = $this->purchaseReceivedRepo->getAllTotalDataBetweenBy($search, $where, $whereBetween);
        return $this->purchaseReceivedRepo->getAllDataBetweenBy($search, $page, $total, $where, $whereBetween);
    }

    public function export(Request $request)
    {
       return $this->exportAsFormat($request,'penerimaan-pembelian.xlsx');
    }

    public function exportCsv(Request $request){
        return $this->exportAsFormat($request,'penerimaan-pembelian.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new PurchaseReceivedExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new PurchaseReceivedExport($data);
        $pdf = PDF::loadView('accounting::purchase.purchase_received_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('penerimaan-pembelian.pdf');
    }

    public function exportReportExcel(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->purchaseReceivedRepo->getAllDataBetweenBy($search, $page, $perpage, $where, $whereBetween);
        return Excel::download(new PurchaseReceivedReportDetailExport($data,$params), 'excel-purchase-received.xlsx');

    }

    public function exportReportPdf(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->purchaseReceivedRepo->getAllDataBetweenBy($search, $page, $perpage, $where, $whereBetween);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::purchase.purchase_received_detail_report', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream('laporan-penerimaan-pembelian.pdf');
        }

        return $pdf->download('laporan-penerimaan-pembelian.pdf');

    }
}
