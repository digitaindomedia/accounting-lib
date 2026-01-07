<?php

namespace Icso\Accounting\Http\Controllers\Penjualan;

use Icso\Accounting\Exports\SalesReturReportExport;
use Icso\Accounting\Exports\SalesReturExport;
use Icso\Accounting\Http\Requests\CreateSalesReturRequest;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
use Icso\Accounting\Models\Penjualan\Retur\SalesReturProduct;
use Icso\Accounting\Repositories\Penjualan\Delivery\DeliveryRepo;
use Icso\Accounting\Repositories\Penjualan\Retur\ReturRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

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
        if (!empty($fromDate) && !empty($untilDate)) {
            $where[] = array(
                'method' => 'whereBetween',
                'value' => array('field' => 'retur_date', 'value' => [$fromDate,$untilDate]));
        }
        return compact('search', 'page', 'perpage', 'where','fromDate','untilDate');
    }

    public function getAllData(Request $request):JsonResponse
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->returRepo->getAllDataBy($search,$page,$perpage,$where);
        $total = $this->returRepo->getAllTotalDataBy($search,$where);
        $hasMore = Helpers::hasMoreData($total, $page, $data);
        
        if(count($data) > 0) {
            // Optimization: Collect all delivery_product_ids to fetch retur quantities in bulk
            $deliveryProductIds = [];
            foreach ($data as $res) {
                if (!empty($res->returproduct)) {
                    foreach ($res->returproduct as $item) {
                        if (!empty($item->delivery_product_id)) {
                            $deliveryProductIds[] = $item->delivery_product_id;
                        }
                    }
                }
            }

            // Fetch all retur quantities for these delivery products in one query
            $returQuantities = [];
            if (!empty($deliveryProductIds)) {
                $returQuantities = SalesReturProduct::whereIn('delivery_product_id', $deliveryProductIds)
                    ->select('delivery_product_id', DB::raw('sum(qty) as total_qty'))
                    ->groupBy('delivery_product_id')
                    ->pluck('total_qty', 'delivery_product_id')
                    ->toArray();
            }

            foreach ($data as $res){
                if(!empty($res->returproduct)){
                    foreach ($res->returproduct as $item){
                        $deliProduct = $item->deliveryproduct;
                        if ($deliProduct) {
                            // Use pre-fetched quantity or 0 if not found
                            $qtyRetur = $returQuantities[$deliProduct->id] ?? 0;
                            
                            $item->qty_bs_retur = ($deliProduct->qty - $qtyRetur) + $item->qty;
                            $item->qty_delivery = $deliProduct->qty;
                        } else {
                            $item->qty_bs_retur = $item->qty;
                            $item->qty_delivery = 0;
                        }
                    }
                }
            }
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['has_more'] = $hasMore;
            $this->data['total'] = $total;
        }else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
            $this->data['data'] = array();
        }
        return response()->json($this->data);
    }

    public function store(CreateSalesReturRequest $request): JsonResponse
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
        $res = $this->returRepo->findOne($id,array(),['vendor','delivery','returproduct','returproduct.product','returproduct.unit','returproduct.tax','returproduct.tax.taxgroup','returproduct.deliveryproduct','returproduct.deliveryproduct.product']);
        if($res){
            if(!empty($res->returproduct)){
                // Optimization for show method as well
                $deliveryProductIds = [];
                foreach ($res->returproduct as $item) {
                    if (!empty($item->delivery_product_id)) {
                        $deliveryProductIds[] = $item->delivery_product_id;
                    }
                }

                $returQuantities = [];
                if (!empty($deliveryProductIds)) {
                    $returQuantities = SalesReturProduct::whereIn('delivery_product_id', $deliveryProductIds)
                        ->select('delivery_product_id', DB::raw('sum(qty) as total_qty'))
                        ->groupBy('delivery_product_id')
                        ->pluck('total_qty', 'delivery_product_id')
                        ->toArray();
                }

                foreach ($res->returproduct as $item){
                    $deliProduct = $item->deliveryproduct;
                    if ($deliProduct) {
                        $qtyRetur = $returQuantities[$deliProduct->id] ?? 0;
                        $item->qty_bs_retur = ($deliProduct->qty - $qtyRetur) + $item->qty;
                        $item->qty_delivery = $deliProduct->qty;
                    } else {
                        $item->qty_bs_retur = $item->qty;
                        $item->qty_delivery = 0;
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
        return $this->exportAsFormat($request,'retur-penjualan.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request,'retur-penjualan.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new SalesReturExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new SalesReturExport($data);
        $pdf = PDF::loadView('accounting::sales.sales_retur_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('retur-penjualan.pdf');
    }

    private function exportReportAsFormat(Request $request, string $filename)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->returRepo->getAllDataBy($search,$page,$perpage,$where);
        return Excel::download(new SalesReturReportExport($data,$params), $filename);
    }

    public function exportReportExcel(Request $request)
    {
        return $this->exportReportAsFormat($request,'laporan-retur-penjualan.xlsx');
    }

    public function exportReportPdf(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        // Reuse the same dataset as the on-screen report
        $data = $this->returRepo->getAllDataBy($search, $page, $perpage, $where);

        // Render the same Blade report view to PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('accounting::sales.sales_retur_report', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream('laporan-retur-penjualan.pdf');
        }

        return $pdf->download('laporan-retur-penjualan.pdf');
    }
}
