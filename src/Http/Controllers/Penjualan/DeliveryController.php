<?php

namespace Icso\Accounting\Http\Controllers\Penjualan;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Enums\StatusEnum;
use Icso\Accounting\Exports\SalesDeliveryExport;
use Icso\Accounting\Exports\SalesDeliveryReportDetail;
use Icso\Accounting\Http\Requests\CreateSalesDeliveryRequest;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Icso\Accounting\Repositories\Penjualan\Delivery\DeliveryRepo;
use Icso\Accounting\Repositories\Penjualan\Order\SalesOrderRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\ProductType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
class DeliveryController extends Controller
{
    protected $deliveryRepo;

    public function __construct(DeliveryRepo $deliveryRepo)
    {
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
        $orderId = $request->order_id;

        $where = array();
        if (!empty($vendorId)) {
            $where[] = array(
                'method' => 'where',
                'value' => [['vendor_id','=',$vendorId]]);
        }
        if (!empty($orderId)) {
            $where[] = array(
                'method' => 'where',
                'value' => [['order_id','=',$orderId]]);
        }
        if (!empty($fromDate) && !empty($untilDate)) {
            $where[] = array(
                'method' => 'whereBetween',
                'value' => array('field' => 'delivery_date', 'value' => [$fromDate,$untilDate]));
        }
        return compact('search', 'page', 'perpage', 'where','fromDate','untilDate');
    }

    public function getAllData(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->deliveryRepo->getAllDataBy($search,$page,$perpage,$where);
        $total = $this->deliveryRepo->getAllTotalDataBy($search, $where);
        $hasMore = Helpers::hasMoreData($total,$page,$data);
        if(count($data) > 0) {
            foreach ($data as $item){
                if(!empty($item->deliveryproduct)){
                    if(count($item->deliveryproduct) > 0){
                        foreach ($item->deliveryproduct as $value){
                            $qtyRetur = $this->deliveryRepo->getQtyRetur($value->id);
                            $value->qty_bs_retur = $value->qty - $qtyRetur;
                        }
                    }
                }
            }
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

    public function store(CreateSalesDeliveryRequest $request): \Illuminate\Http\JsonResponse
    {
        $res = $this->deliveryRepo->store($request);
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

    public function show(Request $request): \Illuminate\Http\JsonResponse
    {
        $res = $this->deliveryRepo->findOne($request->id,array(),['order', 'vendor', 'warehouse','deliveryproduct','deliveryproduct.items','deliveryproduct.unit','deliveryproduct.product','deliveryproduct.orderproduct']);
        if($res){
            if(!empty($res->deliveryproduct)){
                foreach ($res->deliveryproduct as $item){
                    $countDelivered = $this->deliveryRepo->getDeliveredProduct($item->delivery_id,$item->product_id, $item->unit_id);
                    $item->qty_delivered = $countDelivered;
                }
            }
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
            $find = $this->deliveryRepo->findOne($id);
            $orderId = $find->order_id ?? null;

            $this->deliveryRepo->deleteAdditional($id);
            $this->deliveryRepo->delete($id);

            $this->deliveryRepo->resetSalesOrderStatus($orderId);
            DB::commit();
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil dihapus';
            $this->data['data'] = array();
        }
        catch (\Exception $e) {
            DB::rollback();
            Log::error($e->getMessage());
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
                    $find = $this->deliveryRepo->findOne($id);
                    $orderId = $find->order_id ?? null;
                    $this->deliveryRepo->deleteAdditional($id);
                    $this->deliveryRepo->delete($id);
                    $this->deliveryRepo->resetSalesOrderStatus($orderId);
                    DB::commit();
                    $successDelete = $successDelete + 1;
                }
                catch (\Exception $e) {
                    DB::rollback();
                    Log::error($e->getMessage());
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

    public function completion(): \Illuminate\Http\JsonResponse
    {
        $completed = SalesDelivery::whereHas('order', function ($query) {
            $query->where('order_type', ProductType::ITEM)->whereIn('order_status',[StatusEnum::INVOICE]);
        })->count();
        $outstanding = SalesDelivery::whereHas('order', function ($query) {
            $query->where('order_type', ProductType::ITEM)->whereIn('order_status',[StatusEnum::DELIVERY, StatusEnum::PARSIAL_DELIVERY]);
        })->count();
        $all = SalesDelivery::count();
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
        $total = $this->deliveryRepo->getAllTotalDataBy($search, $where);
        $data = $this->deliveryRepo->getAllDataBy($search,$page,$total,$where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request,'pengiriman-penjualan.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request,'pengiriman-penjualan.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new SalesDeliveryExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new SalesDeliveryExport($data);
        $pdf = PDF::loadView('accounting::sales.sales_delivery_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('pengiriman-penjualan.pdf');
    }

    public function exportReportExcel(Request $request)
    {
        return $this->exportReportAsFormat($request,'laporan-pengiriman-penjualan.xlsx');
    }

    public function exportReportCsv(Request $request){
        return $this->exportReportAsFormat($request,'laporan-pengiriman-penjualan.csv');
    }

    public function exportReportPdf(Request $request){
        return $this->exportReportAsFormat($request,'laporan-pengiriman-penjualan.pdf', 'pdf');
    }

    private function exportReportAsFormat(Request $request, string $filename,string $type = 'excel')
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->deliveryRepo->getAllDataBy($search, $page, $perpage, $where);
        if($type == 'excel'){
            return $this->downloadExcel($data, $params, $filename);
        } else {
            return $this->downloadPdf($request, $data, $params, $filename);
        }
    }

    private function downloadExcel($data, $params, $filename){
        return Excel::download(new SalesDeliveryReportDetail($data,$params), $filename);
    }

    private function downloadPdf(Request $request, $data, $params, $filename){
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::sales.sales_delivery_report_detail', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }
}
