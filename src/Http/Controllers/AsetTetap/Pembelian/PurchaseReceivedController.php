<?php

namespace Icso\Accounting\Http\Controllers\AsetTetap\Pembelian;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\PurchasePenerimaanAsetTetapExport;
use Icso\Accounting\Http\Requests\CreatePurchaseReceivedAsetTetapRequest;
use Icso\Accounting\Repositories\AsetTetap\Pembelian\ReceiveRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Routing\Controller;

class PurchaseReceivedController extends Controller
{
    protected $purchaseReceiveRepo;
    public function __construct(ReceiveRepo $purchaseReceiveRepo)
    {
        $this->purchaseReceiveRepo = $purchaseReceiveRepo;
    }

    private function setQueryParameters(Request $request)
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $where = array();
        if(!empty($fromDate) && !empty($untilDate)){
            $where[] = array(
                'method' => 'whereBetween',
                'value' => array('field' => 'aset_tetap_date', 'value' => [$fromDate,$untilDate]));
        }
        return compact('search', 'page', 'perpage', 'where');
    }

    public function getAllData(Request $request): \Illuminate\Http\JsonResponse
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $data = $this->purchaseReceiveRepo->getAllDataBy($search,$page,$perpage,$where);
        $total = $this->purchaseReceiveRepo->getAllTotalDataBy($search, $where);
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

    public function store(CreatePurchaseReceivedAsetTetapRequest $request): \Illuminate\Http\JsonResponse
    {
        $res = $this->purchaseReceiveRepo->store($request);
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
        $res = $this->purchaseReceiveRepo->findOne($request->id,array(),['order']);
        if($res){
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
            $this->purchaseReceiveRepo->deleteAdditional($id);
            $this->purchaseReceiveRepo->delete($id);
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
                    $this->purchaseReceiveRepo->deleteAdditional($id);
                    $this->purchaseReceiveRepo->delete($id);
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
        $total = $this->purchaseReceiveRepo->getAllTotalDataBy($search, $where);
        $data = $this->purchaseReceiveRepo->getAllDataBy($search,$page,$total,$where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request, 'penerimaan-pembelian-aset-tetap.xlsx');
    }

    public function exportCsv(Request $request){
        return $this->exportAsFormat($request, 'uang-muka-pembelian-aset-tetap.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new PurchasePenerimaanAsetTetapExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new PurchasePenerimaanAsetTetapExport($data);
        $pdf = PDF::loadView('accounting::fixasset.purchase_received_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('penerimaan-pembelian-aset-tetap.pdf');
    }
}
