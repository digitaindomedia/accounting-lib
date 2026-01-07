<?php

namespace Icso\Accounting\Http\Controllers\Persediaan;

use Barryvdh\DomPDF\Facade\Pdf;
use Icso\Accounting\Exports\AdjustmentStockExport;
use Icso\Accounting\Exports\AdjustmentStockReportExport;
use Icso\Accounting\Exports\SampleAdjustmentExport;
use Icso\Accounting\Http\Requests\CreateAdjustmentRequest;
use Icso\Accounting\Imports\AdjustmentImport;
use Icso\Accounting\Repositories\Persediaan\Adjustment\AdjustmentRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdjustmentController extends Controller
{
    protected AdjustmentRepo $adjustmentRepo;
    protected array $data = [];

    public function __construct(AdjustmentRepo $adjustmentRepo)
    {
        $this->adjustmentRepo = $adjustmentRepo;
    }

    private function setQueryParameters(Request $request): array
    {
        $search = $request->q;
        $page = $request->page;
        $perpage = $request->perpage;
        $fromDate = $request->from_date;
        $untilDate = $request->until_date;
        $warehouseId = $request->warehouse_id;

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
                'value' => ['field' => 'adjustment_date', 'value' => [$fromDate, $untilDate]]
            ];
        }
        return compact('search', 'page', 'perpage', 'where', 'fromDate', 'untilDate');
    }

    public function getAllData(Request $request): JsonResponse
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->adjustmentRepo->getAllDataBy($search, $page, $perpage, $where);
        $total = $this->adjustmentRepo->getAllTotalDataBy($search, $where);
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

    public function store(CreateAdjustmentRequest $request): JsonResponse
    {
        try {
            $res = $this->adjustmentRepo->store($request);
            if ($res) {
                $this->data['status'] = true;
                $this->data['message'] = 'Data berhasil disimpan';
                $this->data['data'] = '';
            } else {
                $this->data['status'] = false;
                $this->data['message'] = "Data gagal disimpan";
                $this->data['data'] = '';
            }
        } catch (\Exception $e) {
            Log::error("Error storing adjustment: " . $e->getMessage());
            $this->data['status'] = false;
            $this->data['message'] = "Terjadi kesalahan saat menyimpan data";
            $this->data['data'] = '';
        }
        return response()->json($this->data);
    }

    public function show(Request $request): JsonResponse
    {
        $id = $request->id;
        if (empty($id)) {
             return response()->json(['status' => false, 'message' => 'ID tidak valid', 'data' => '']);
        }

        $res = $this->adjustmentRepo->findOne($id, [], [
            'warehouse',
            'coa_adjustment',
            'adjustmentproduct',
            'adjustmentproduct.product',
            'adjustmentproduct.coa',
            'adjustmentproduct.unit'
        ]);

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
        $id = $request->id;
        if (empty($id)) {
             return response()->json(['status' => false, 'message' => 'ID tidak valid', 'data' => []]);
        }

        DB::beginTransaction();
        try {
            $this->adjustmentRepo->deleteAdditional($id);
            $this->adjustmentRepo->delete($id);
            DB::commit();
            
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil dihapus';
            $this->data['data'] = [];
        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Error deleting adjustment $id: " . $e->getMessage());
            $this->data['status'] = false;
            $this->data['message'] = 'Data gagal dihapus';
            $this->data['data'] = [];
        }
        return response()->json($this->data);
    }

    public function deleteAll(Request $request): JsonResponse
    {
        $ids = $request->ids;
        if (!is_array($ids) || empty($ids)) {
             return response()->json(['status' => false, 'message' => 'Data ID tidak valid', 'data' => []]);
        }

        $successDelete = 0;
        $failedDelete = 0;

        foreach ($ids as $id) {
            DB::beginTransaction();
            try {
                $this->adjustmentRepo->deleteAdditional($id);
                $this->adjustmentRepo->delete($id);
                DB::commit();
                $successDelete++;
            } catch (\Exception $e) {
                DB::rollback();
                Log::error("Error deleting adjustment $id in bulk: " . $e->getMessage());
                $failedDelete++;
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

    public function downloadSample(Request $request): BinaryFileResponse
    {
        return Excel::download(new SampleAdjustmentExport(), 'sample_penyesuaian_stok.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        try {
            $userId = $request->user_id;
            $import = new AdjustmentImport($userId);
            Excel::import($import, $request->file('file'));

            if ($errors = $import->getErrors()) {
                return response()->json([
                    'status' => false, 
                    'success' => $import->getSuccessCount(),
                    'messageError' => $errors,
                    'errors' => count($errors), 
                    'imported' => $import->getTotalRows()
                ]);
            }

            return response()->json([
                'status' => true,
                'success' => $import->getSuccessCount(),
                'errors' => count($import->getErrors()), 
                'message' => 'File berhasil import', 
                'imported' => $import->getTotalRows()
            ], 200);
        } catch (\Exception $e) {
            Log::error("Import error: " . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan saat import file: ' . $e->getMessage()
            ], 500);
        }
    }

    private function prepareExportData(Request $request)
    {
        $params = $this->setQueryParameters($request);
        extract($params);
        $total = $this->adjustmentRepo->getAllTotalDataBy($search, $where);
        $limit = $total > 0 ? $total : 1; 
        
        return $this->adjustmentRepo->getAllDataBy($search, $page, $limit, $where);
    }

    public function export(Request $request): BinaryFileResponse
    {
        return $this->exportAsExport($request, 'penyesuaian-stok.xlsx');
    }

    public function exportCsv(Request $request): BinaryFileResponse
    {
        return $this->exportAsExport($request, 'penyesuaian-stok.csv');
    }

    private function exportAsExport(Request $request, string $filename): BinaryFileResponse
    {
        $data = $this->prepareExportData($request);
        return Excel::download(new AdjustmentStockExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->prepareExportData($request);
        $export = new AdjustmentStockExport($data);
        $pdf = Pdf::loadView('accounting::stock.adjustment_stock_pdf', ['arrData' => $export->collection()]);
        return $pdf->download('penyesuaian-stok.pdf');
    }

    private function exportReportAsFormat(Request $request, string $filename, string $type = 'excel')
    {
        $params = $this->setQueryParameters($request);
        extract($params);

        $data = $this->adjustmentRepo->getAllDataBy($search, $page, $perpage, $where);
        
        if ($type == 'excel') {
            return $this->downloadExcel($data, $params, $filename);
        } else {
            return $this->downloadPdf($request, $data, $params, $filename);
        }
    }

    private function downloadExcel($data, $params, $filename): BinaryFileResponse
    {
        return Excel::download(new AdjustmentStockReportExport($data, $params), $filename);
    }

    private function downloadPdf(Request $request, $data, $params, $filename)
    {
        $pdf = Pdf::loadView('accounting::stock.adjustment_stock_report', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'portrait');

        if ($request->get('mode') === 'print') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    public function exportReportExcel(Request $request): BinaryFileResponse
    {
        return $this->exportReportAsFormat($request, 'laporan-penyesuaian-stok.xlsx');
    }

    public function exportReportCsv(Request $request): BinaryFileResponse
    {
        return $this->exportReportAsFormat($request, 'laporan-penyesuaian-stok.csv');
    }

    public function exportReportPdf(Request $request)
    {
        return $this->exportReportAsFormat($request, 'laporan-penyesuaian-stok.pdf', 'pdf');
    }
}
