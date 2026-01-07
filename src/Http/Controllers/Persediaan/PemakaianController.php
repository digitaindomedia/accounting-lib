<?php

namespace Icso\Accounting\Http\Controllers\Persediaan;

use Icso\Accounting\Exports\UsageStockReportExport;
use Icso\Accounting\Exports\SamplePemakaianExport;
use Icso\Accounting\Exports\UsageStockExport;
use Icso\Accounting\Http\Requests\CreatePemakaianRequest;
use Icso\Accounting\Imports\PemakaianImport;
use Icso\Accounting\Repositories\Persediaan\Pemakaian\PemakaianRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PemakaianController extends Controller
{
    protected $pemakaianRepo;
    protected $data = [];

    public function __construct(PemakaianRepo $pemakaianRepo)
    {
        $this->pemakaianRepo = $pemakaianRepo;
    }

    private function setQueryParameters(Request $request): array
    {
        $search = $request->input('q');
        $page = $request->input('page');
        $perpage = $request->input('perpage');
        $fromDate = $request->input('from_date');
        $untilDate = $request->input('until_date');
        $warehouseId = $request->input('warehouse_id');

        $where = [];
        if (!empty($warehouseId)) {
            $where[] = [
                'method' => 'where',
                'value' => [['warehouse_id', '=', $warehouseId]]
            ];
        }
        if (!empty($fromDate) && !empty($untilDate)) {
            $where[] = [
                'method' => 'whereBetween',
                'value' => ['field' => 'usage_date', 'value' => [$fromDate, $untilDate]]
            ];
        }
        return compact('search', 'page', 'perpage', 'where');
    }

    public function getAllData(Request $request): JsonResponse
    {
        $params = $this->setQueryParameters($request);
        $search = $params['search'];
        $page = $params['page'];
        $perpage = $params['perpage'];
        $where = $params['where'];

        $data = $this->pemakaianRepo->getAllDataBy($search, $page, $perpage, $where);
        $total = $this->pemakaianRepo->getAllTotalDataBy($search, $where);
        $hasMore = Helpers::hasMoreData($total, $page, $data);

        if (count($data) > 0) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $data;
            $this->data['has_more'] = $hasMore;
            $this->data['total'] = $total;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data tidak ditemukan';
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }

    public function store(CreatePemakaianRequest $request): JsonResponse
    {
        $res = $this->pemakaianRepo->store($request);
        if ($res) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';
            $this->data['data'] = '';
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal disimpan";
            $this->data['data'] = '';
        }
        return response()->json($this->data, $res ? 200 : 500);
    }

    public function show(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['status' => false, 'message' => 'ID tidak ditemukan', 'data' => ''], 400);
        }

        $res = $this->pemakaianRepo->findOne($id, [], ['warehouse', 'coa_stock', 'stockusageproduct', 'stockusageproduct.product', 'stockusageproduct.coa', 'stockusageproduct.unit']);
        if ($res) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data tidak ditemukan";
            $this->data['data'] = '';
        }
        return response()->json($this->data);
    }

    public function destroy(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['status' => false, 'message' => 'ID tidak ditemukan', 'data' => ''], 400);
        }

        DB::beginTransaction();
        try {
            $this->pemakaianRepo->deleteAdditional($id);
            $this->pemakaianRepo->delete($id);
            DB::commit();
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil dihapus';
            $this->data['data'] = [];
        } catch (\Exception $e) {
            DB::rollback();
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }

    public function deleteAll(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array'
        ]);

        $reqData = $request->input('ids');
        $successDelete = 0;
        $failedDelete = 0;

        if (count($reqData) > 0) {
            foreach ($reqData as $id) {
                DB::beginTransaction();
                try {
                    $this->pemakaianRepo->deleteAdditional($id);
                    $this->pemakaianRepo->delete($id);
                    DB::commit();
                    $successDelete++;
                } catch (\Exception $e) {
                    DB::rollback();
                    $failedDelete++;
                }
            }
        }

        if ($successDelete > 0) {
            $this->data['status'] = true;
            $this->data['message'] = "$successDelete Data berhasil dihapus <br /> $failedDelete Data tidak bisa dihapus";
            $this->data['data'] = [];
        } else {
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }

    public function downloadSample(Request $request)
    {
        return Excel::download(new SamplePemakaianExport(), 'sample_pemakaian_stok.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);
        $userId = $request->input('user_id');
        $import = new PemakaianImport($userId);
        Excel::import($import, $request->file('file'));

        if ($errors = $import->getErrors()) {
            return response()->json(['status' => false, 'success' => $import->getSuccessCount(), 'messageError' => $errors, 'errors' => count($errors), 'imported' => $import->getTotalRows()]);
        }

        return response()->json(['status' => true, 'success' => $import->getSuccessCount(), 'errors' => count($import->getErrors()), 'message' => 'Import file berhasil', 'imported' => $import->getTotalRows()], 200);
    }

    private function prepareExportData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        $search = $params['search'];
        $page = $params['page'];
        $where = $params['where'];
        
        $total = $this->pemakaianRepo->getAllTotalDataBy($search, $where);
        // Note: getAllDataBy expects perpage, passing total to get all data
        $data = $this->pemakaianRepo->getAllDataBy($search, $page, $total, $where);
        return $data;
    }

    public function export(Request $request)
    {
        return $this->exportAsFormat($request, 'pemakaian-stok.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsFormat($request, 'pemakaian-stok.csv');
    }

    private function exportAsFormat(Request $request, string $filename)
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new UsageStockExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new UsageStockExport($data);
        $pdf = PDF::loadView('accounting::stock.usage_stock_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('pemakaian-stok.pdf');
    }

    private function exportReportAsFormat(Request $request, string $filename, string $type = 'excel')
    {
        $params = $this->setQueryParameters($request);
        $search = $params['search'];
        $page = $params['page'];
        $perpage = $params['perpage'];
        $where = $params['where'];

        $data = $this->pemakaianRepo->getAllDataBy($search, $page, $perpage, $where);
        if ($type == 'excel') {
            return $this->downloadExcel($data, $params, $filename);
        } else {
            return $this->downloadPdf($request, $data, $params, $filename);
        }
    }

    private function downloadExcel($data, $params, $filename)
    {
        return Excel::download(new UsageStockReportExport($data, $params), $filename);
    }

    private function downloadPdf(Request $request, $data, $params, $filename)
    {
        $pdf = Pdf::loadView('accounting::stock.usage_stock_report', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->input('mode') === 'print') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    public function exportReportExcel(Request $request)
    {
        return $this->exportReportAsFormat($request, 'laporan-pemakaian-stok.xlsx');
    }

    public function exportReportCsv(Request $request)
    {
        return $this->exportReportAsFormat($request, 'laporan-pemakaian-stok.csv');
    }

    public function exportReportPdf(Request $request)
    {
        return $this->exportReportAsFormat($request, 'laporan-pemakaian-stok.pdf', 'pdf');
    }
}
