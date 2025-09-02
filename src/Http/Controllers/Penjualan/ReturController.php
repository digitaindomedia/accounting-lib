<?php

namespace Icso\Accounting\Http\Controllers\Penjualan;

use Icso\Accounting\Exports\SalesReturExport;
use Icso\Accounting\Http\Requests\CreateSalesReturRequest;
use Icso\Accounting\Models\Penjualan\Pengiriman\SalesDelivery;
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
        $where=array();
        if(!empty($vendorId)){
            $where[] = array(
                'method' => 'where',
                'value' => [['vendor_id','=',$vendorId]]);
        }
        return compact('search', 'page', 'perpage', 'where');
    }

    public function getAllData(Request $request):JsonResponse
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->returRepo->getAllDataBy($search,$page,$perpage,$where);
        $total = $this->returRepo->getAllTotalDataBy($search,$where);
        $hasMore = Helpers::hasMoreData($total, $page, $data);
        $deliveryRepo = new DeliveryRepo(new SalesDelivery());
        if(count($data) > 0) {
            foreach ($data as $res){
                if(!empty($res->returproduct)){

                    foreach ($res->returproduct as $item){
                        $deliProduct = $item->deliveryproduct;
                        $qtyRetur = $deliveryRepo->getQtyRetur($deliProduct->id);
                        $item->qty_bs_retur = ($deliProduct->qty - $qtyRetur) + $item->qty;
                        $item->qty_delivery = $deliProduct->qty;
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
                $deliveryRepo = new DeliveryRepo(new SalesDelivery());
                foreach ($res->returproduct as $item){
                    $deliProduct = $item->deliveryproduct;
                    $qtyRetur = $deliveryRepo->getQtyRetur($deliProduct->id);
                    $item->qty_bs_retur = ($deliProduct->qty - $qtyRetur) + $item->qty;
                    $item->qty_delivery = $deliProduct->qty;
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
        $pdf = PDF::loadView('sales/sales_retur_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('retur-penjualan.pdf');
    }
}
