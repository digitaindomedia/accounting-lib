<?php

namespace Icso\Accounting\Http\Controllers\Persediaan;

use Icso\Accounting\Exports\UsageStockReportExport;
use Icso\Accounting\Exports\SamplePemakaianExport;
use Icso\Accounting\Exports\UsageStockExport;
use Icso\Accounting\Http\Requests\CreatePemakaianRequest;
use Icso\Accounting\Imports\PemakaianImport;
use Icso\Accounting\Models\ImportLog;
use Icso\Accounting\Models\ImportLogDetail;
use Icso\Accounting\Models\Persediaan\StockUsage;
use Icso\Accounting\Repositories\Persediaan\Pemakaian\PemakaianRepo;
use Icso\Accounting\Utils\Helpers;
use Icso\Accounting\Utils\TransactionsCode;
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
        return response()->json($this->data);
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

        try {
            $deleted = $this->pemakaianRepo->destroy((int) $id, (int) $request->user_id);
            if ($deleted) {
                $this->data['status'] = true;
                $this->data['message'] = 'Data berhasil dihapus';
                $this->data['data'] = [];
            } else {
                $this->data['status'] = false;
                $this->data['message'] = 'Data gagal dihapus';
                $this->data['data'] = [];
            }
        } catch (\Exception $e) {
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
                try {
                    $pemakaianId = is_array($id) ? ($id['id'] ?? null) : ($id->id ?? $id);
                    if (!$pemakaianId) {
                        $failedDelete++;
                        continue;
                    }

                    $deleted = $this->pemakaianRepo->destroy((int) $pemakaianId, (int) $request->user_id);
                    if ($deleted) {
                        $successDelete++;
                    } else {
                        $failedDelete++;
                    }
                } catch (\Exception $e) {
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
        $importLogId = $this->createImportLog(
            $userId,
            TransactionsCode::PEMAKAIAN_STOCK,
            $import->getImportedIds()
        );

        if ($errors = $import->getErrors()) {
            return response()->json(['status' => false, 'success' => $import->getSuccessCount(), 'messageError' => $errors, 'errors' => count($errors), 'imported' => $import->getTotalRows(), 'import_log_id' => $importLogId]);
        }

        return response()->json(['status' => true, 'success' => $import->getSuccessCount(), 'errors' => count($import->getErrors()), 'message' => 'Import file berhasil', 'imported' => $import->getTotalRows(), 'import_log_id' => $importLogId], 200);
    }

    private function createImportLog($userId, string $transactionType, array $transactionIds)
    {
        $transactionIds = array_values(array_unique(array_filter($transactionIds)));
        if (empty($transactionIds)) {
            return null;
        }

        return DB::transaction(function () use ($userId, $transactionType, $transactionIds) {
            $importLog = ImportLog::create([
                'import_at' => date('Y-m-d H:i:s'),
                'user_id' => !empty($userId) ? $userId : null,
                'transaction_type' => $transactionType,
                'total_detail' => count($transactionIds),
            ]);

            $details = array_map(function ($transactionId) use ($importLog) {
                return [
                    'import_log_id' => $importLog->id,
                    'transaksi_id' => $transactionId,
                ];
            }, $transactionIds);

            ImportLogDetail::insert($details);

            return $importLog->id;
        });
    }

    public function getImportLogs(Request $request): JsonResponse
    {
        $page = (int) ($request->input('page') ?? 0);
        $perpage = (int) ($request->input('perpage') ?? 10);
        $perpage = $perpage > 0 ? $perpage : 10;

        $query = ImportLog::where('transaction_type', TransactionsCode::PEMAKAIAN_STOCK)
            ->withCount('details')
            ->orderBy('import_at', 'desc')
            ->orderBy('id', 'desc');

        $total = (clone $query)->count();
        $data = $query->offset($page)->limit($perpage)->get();

        return response()->json([
            'status' => count($data) > 0,
            'message' => count($data) > 0 ? 'Data berhasil ditemukan' : 'Data tidak ditemukan',
            'data' => $data,
            'total' => $total,
            'has_more' => Helpers::hasMoreData($total, $page, $data),
        ]);
    }

    public function showImportLog(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $data = ImportLog::where('transaction_type', TransactionsCode::PEMAKAIAN_STOCK)
            ->with([
                'details',
                'details.stockUsage',
                'details.stockUsage.warehouse',
                'details.stockUsage.coa_stock',
                'details.stockUsage.stockusageproduct',
                'details.stockUsage.stockusageproduct.product',
                'details.stockUsage.stockusageproduct.coa',
                'details.stockUsage.stockusageproduct.unit'
            ])
            ->find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan',
                'data' => null,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Data berhasil ditemukan',
            'data' => $data,
        ]);
    }

    public function deleteImportLogData(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $userId = (int) ($request->input('user_id') ?? 0);
        $importLog = ImportLog::where('transaction_type', TransactionsCode::PEMAKAIAN_STOCK)
            ->with('details')
            ->find($id);

        if (!$importLog) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan',
                'data' => [],
            ]);
        }

        $successDelete = 0;
        $failedDelete = 0;
        $missingData = 0;
        $deletedDetailIds = [];

        foreach ($importLog->details as $detail) {
            $transactionId = (int) $detail->transaksi_id;

            if (!StockUsage::whereKey($transactionId)->exists()) {
                $missingData++;
                $deletedDetailIds[] = $detail->id;
                continue;
            }

            if ($this->pemakaianRepo->destroy($transactionId, $userId)) {
                $successDelete++;
                $deletedDetailIds[] = $detail->id;
            } else {
                $failedDelete++;
            }
        }

        if (!empty($deletedDetailIds)) {
            ImportLogDetail::whereIn('id', $deletedDetailIds)->delete();
        }

        $remaining = ImportLogDetail::where('import_log_id', $importLog->id)->count();
        if ($remaining === 0) {
            $importLog->delete();
        } else {
            $importLog->total_detail = $remaining;
            $importLog->save();
        }

        $message = "$successDelete Data berhasil dihapus <br /> $failedDelete Data tidak bisa dihapus";
        if ($missingData > 0) {
            $message .= " <br /> $missingData Data sudah tidak ada";
        }

        return response()->json([
            'status' => ($successDelete + $missingData) > 0,
            'message' => $message,
            'data' => [],
        ]);
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
