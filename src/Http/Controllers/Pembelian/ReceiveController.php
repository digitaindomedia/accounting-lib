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
use Illuminate\Support\Facades\Log;

class ReceiveController extends Controller
{
    protected $purchaseReceivedRepo;
    protected $data = [];

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
        $res = $this->purchaseReceivedRepo->findOne($request->id,array(),['vendor', 'order', 'warehouse','invoicereceived','receiveproduct','receiveproduct.items', 'receiveproduct.product','receiveproduct.orderproduct','receiveproduct.tax','receiveproduct.unit']);
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
        try {
            $deleted = $this->purchaseReceivedRepo->destroy($id, (int) $request->user_id);
            if ($deleted) {
                $this->data['status'] = true;
                $this->data['message'] = 'Data berhasil dihapus';
                $this->data['data'] = array();
            } else {
                Log::error('[ReceiveController][destroy] Penerimaan gagal dihapus', [
                    'id' => $id,
                    'user_id' => $request->user_id,
                ]);
                $this->data['status'] = false;
                $this->data['message'] = 'Data gagal dihapus';
                $this->data['data'] = array();
            }
        }
        catch (\Throwable $e) {
            Log::error('[ReceiveController][destroy] Error hapus penerimaan', [
                'id' => $id,
                'user_id' => $request->user_id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
            $this->data['data'] = array();
        }
        return response()->json($this->data);
    }

    public function deleteAll(Request $request)
    {
        try {
            $reqData = $request->input('ids', $request->input('params.ids', []));
            $userId = (int) $request->user_id;
            $successDelete = 0;
            $failedDelete = 0;

            if (!is_array($reqData)) {
                $reqData = json_decode(json_encode($reqData), true) ?: [];
            }

            foreach ($reqData as $id) {
                $receiveId = is_array($id) ? ($id['id'] ?? null) : $id;
                if (!$receiveId) {
                    $failedDelete++;
                    Log::error('[ReceiveController][deleteAll] ID penerimaan tidak valid', [
                        'payload_id' => $id,
                        'user_id' => $userId,
                    ]);
                    continue;
                }

                try {
                    $deleted = $this->purchaseReceivedRepo->destroy((int) $receiveId, $userId);
                    if ($deleted) {
                        $successDelete++;
                    } else {
                        $failedDelete++;
                        Log::error('[ReceiveController][deleteAll] Penerimaan gagal dihapus', [
                            'receive_id' => (int) $receiveId,
                            'user_id' => $userId,
                        ]);
                    }
                }
                catch (\Throwable $e) {
                    $failedDelete++;
                    Log::error('[ReceiveController][deleteAll] Error hapus penerimaan', [
                        'receive_id' => (int) $receiveId,
                        'user_id' => $userId,
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            if($successDelete > 0) {
                $this->data['status'] = true;
                $this->data['message'] = "$successDelete Data berhasil dihapus <br /> $failedDelete Data tidak bisa dihapus";
                $this->data['data'] = array();
            } else {
                Log::error('[ReceiveController][deleteAll] Semua penerimaan gagal dihapus', [
                    'ids' => $reqData,
                    'success_delete' => $successDelete,
                    'failed_delete' => $failedDelete,
                    'user_id' => $userId,
                ]);
                $this->data['status'] = false;
                $this->data['message'] = $failedDelete > 0 ? "$failedDelete Data tidak bisa dihapus" : 'Data gagal dihapus';
                $this->data['data'] = array();
            }
        } catch (\Throwable $e) {
            Log::error('[ReceiveController][deleteAll] Error hapus semua penerimaan', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ids' => $request->input('ids', $request->input('params.ids', [])),
                'user_id' => $request->user_id,
            ]);
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
