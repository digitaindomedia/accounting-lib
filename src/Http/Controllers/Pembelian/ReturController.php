<?php

namespace Icso\Accounting\Http\Controllers\Pembelian;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\PurchaseReturExport;
use Icso\Accounting\Exports\PurchaseReturReportExport;
use Icso\Accounting\Http\Requests\CreatePurchaseReturRequest;
use Icso\Accounting\Models\Pembelian\Penerimaan\PurchaseReceived;
use Icso\Accounting\Models\Pembelian\Retur\PurchaseReturProduct;
use Icso\Accounting\Repositories\Pembelian\Received\ReceiveRepo;
use Icso\Accounting\Repositories\Pembelian\Retur\ReturRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class ReturController extends Controller
{
    protected $returRepo;

    public function __construct(ReturRepo $returRepo)
    {
        $this->returRepo = $returRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $vendorId = $request->vendor_id;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $where=array();
        if(!empty($vendorId)){
            $where[] = array(
                'method' => 'where',
                'value' => [['vendor_id','=',$vendorId]]);
        }
        if(!empty($fromDate) && !empty($untilDate)){
            $where[] = array(
                'method' => 'whereBetween',
                'value' => array('field' => 'retur_date', 'value' => [$fromDate,$untilDate]));
        }
        return compact('search', 'page', 'perpage', 'where','fromDate', 'untilDate', 'vendorId');
    }

    public function getAllData(Request $request):JsonResponse
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->returRepo->getAllDataBy($search,$page,$perpage,$where);
        $total = $this->returRepo->getAllTotalDataBy($search,$where);
        $hasMore = Helpers::hasMoreData($total, $page, $data);
        if(count($data) > 0) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['has_more'] = $hasMore;
            $this->data['total'] = $total;
        }else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }

    public function store(CreatePurchaseReturRequest $request): JsonResponse
    {
        $res = $this->returRepo->store($request);
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
        $id = $request->id;
        $res = $this->returRepo->findOne($id,array(),['vendor','receive','returproduct','invoice','returproduct.receiveproduct','returproduct.orderproduct', 'returproduct.product','returproduct.tax','returproduct.tax.taxgroup','returproduct.tax.taxgroup.tax','returproduct.unit']);
        if($res){
            if(!empty($res->returproduct)){
                // Optimization: Pre-fetch summed quantities to avoid N+1 queries
                $receiveProductIds = $res->returproduct->pluck('receive_product_id')->filter()->unique()->toArray();
                $orderProductIds = $res->returproduct->pluck('order_product_id')->filter()->unique()->toArray();

                $qtyReturByReceive = [];
                if (!empty($receiveProductIds)) {
                    $qtyReturByReceive = PurchaseReturProduct::whereIn('receive_product_id', $receiveProductIds)
                        ->groupBy('receive_product_id')
                        ->selectRaw('receive_product_id, sum(qty) as total_qty')
                        ->pluck('total_qty', 'receive_product_id')
                        ->toArray();
                }

                $qtyReturByOrder = [];
                if (!empty($orderProductIds)) {
                    $qtyReturByOrder = PurchaseReturProduct::whereIn('order_product_id', $orderProductIds)
                        ->groupBy('order_product_id')
                        ->selectRaw('order_product_id, sum(qty) as total_qty')
                        ->pluck('total_qty', 'order_product_id')
                        ->toArray();
                }

                foreach ($res->returproduct as $item){
                    $recProduct = $item->receiveproduct;
                    if(!empty($recProduct)){
                        $qtyRetur = $qtyReturByReceive[$recProduct->id] ?? 0;
                        $item->qty_bs_retur = ($recProduct->qty - $qtyRetur) + $item->qty;
                        $item->qty_received = $recProduct->qty;
                    } else {
                        $orderProduct = $item->orderproduct;
                        if ($orderProduct) {
                            $qtyRetur = $qtyReturByOrder[$orderProduct->id] ?? 0;
                            $item->qty_received = $orderProduct->qty;
                            $item->qty_bs_retur = ($orderProduct->qty - $qtyRetur) + $item->qty;
                        } else {
                            $item->qty_received = 0;
                            $item->qty_bs_retur = 0;
                        }
                    }
                }
            }
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        }else {
            $this->data['status'] = false;
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
            $this->returRepo->deleteAdditional($id);
            $this->returRepo->delete($id);
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
                    $this->returRepo->deleteAdditional($id);
                    $this->returRepo->delete($id);
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
        $total = $this->returRepo->getAllTotalDataBy($search,$where);
        $data = $this->returRepo->getAllDataBy($search,$page,$total,$where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request,'retur-pembelian.xlsx');
    }

    public function exportCsv(Request $request )
    {
        return $this->exportAsFormat($request,'retur-pembelian.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new PurchaseReturExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new PurchaseReturExport($data);
        $pdf = PDF::loadView('accounting::purchase.purchase_retur_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('retur-pembelian.pdf');
    }

    private function exportReportAsFormat(Request $request, string $filename)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->returRepo->getAllDataBy($search,$page,$perpage,$where);
        return Excel::download(new PurchaseReturReportExport($data,$params), $filename);
    }

    public function exportReportExcel(Request $request)
    {
        return $this->exportReportAsFormat($request,'excel-purchase-retur.xlsx');
    }

    public function exportReportPdf(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        // Reuse the same dataset as the on-screen report
        $data = $this->returRepo->getAllDataBy($search, $page, $perpage, $where);

        // Render the same Blade report view to PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::purchase.purchase_retur_report', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream('laporan-retur-pembelian.pdf');
        }

        return $pdf->download('laporan-retur-pembelian.pdf');
    }
}
