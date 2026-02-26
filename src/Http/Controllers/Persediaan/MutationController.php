<?php

namespace Icso\Accounting\Http\Controllers\Persediaan;

use Icso\Accounting\Exports\MutationStockExport;
use Icso\Accounting\Exports\MutationStockReportExport;
use Icso\Accounting\Http\Requests\CreateMutationRequest;
use Icso\Accounting\Repositories\Persediaan\Mutation\MutationRepo;
use Icso\Accounting\Utils\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class MutationController extends Controller
{
    protected $mutationRepo;
    protected $data = [];

    public function __construct(MutationRepo $mutationRepo)
    {
        $this->mutationRepo = $mutationRepo;
    }

    private function setQueryParameters(Request $request): array
    {
        $search = $request->input('q');
        $page = $request->input('page');
        $perpage = $request->input('perpage');
        $fromDate = $request->input('from_date');
        $untilDate = $request->input('until_date');
        $warehouseId = $request->input('warehouse_id');
        $mutationType = $request->input('mutation_type');
        $excludeStatusMutation = $request->input('exclude_status_mutation');

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
        if (!empty($mutationType)) {
            $where[] = [
                'method' => 'where',
                'value' => [['mutation_type', '=', $mutationType]]
            ];
        }
        if (!empty($excludeStatusMutation)) {
            $where[] = [
                'method' => 'where',
                'value' => [['status_mutation', '!=', $excludeStatusMutation]]
            ];
        }
        return compact('search', 'page', 'perpage', 'where', 'fromDate', 'untilDate');
    }

    public function getAllData(Request $request): JsonResponse
    {
        $params = $this->setQueryParameters($request);
        $search = $params['search'];
        $page = $params['page'];
        $perpage = $params['perpage'];
        $where = $params['where'];

        $data = $this->mutationRepo->getAllDataBy($search, $page, $perpage, $where);
        $total = $this->mutationRepo->getAllTotalDataBy($search, $where);
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

    public function store(CreateMutationRequest $request): JsonResponse
    {
        $res = $this->mutationRepo->store($request);
        if ($res) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil disimpan';
            $this->data['data'] = '';
            return response()->json($this->data, 200);
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal disimpan";
            $this->data['data'] = '';
            return response()->json($this->data, 500);
        }
    }

    public function show(Request $request): JsonResponse
    {
        $id = $request->input('id');
        if (!$id) {
            return response()->json(['status' => false, 'message' => 'ID tidak ditemukan', 'data' => ''], 400);
        }

        $res = $this->mutationRepo->findOne($id, [], ['fromwarehouse', 'mutation','towarehouse', 'mutationproduct','salesquotation', 'mutationproduct.product', 'mutationproduct.unit']);
        if ($res) {
            $this->data['status'] = true;
            $this->data['message'] = 'Data berhasil ditemukan';
            $this->data['data'] = $res;
        } else {
            $this->data['status'] = false;
            $this->data['message'] = "Data gagal ditemukan";
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
            $this->mutationRepo->deleteAdditional($id);
            $this->mutationRepo->delete($id);
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
                    $this->mutationRepo->deleteAdditional($id);
                    $this->mutationRepo->delete($id);
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

    private function exportReportAsFormat(Request $request, string $filename, string $type = 'excel')
    {
        $params = $this->setQueryParameters($request);
        $search = $params['search'];
        $where = $params['where'];

        // Note: Using hardcoded limit 10000 as per original code, but could be improved
        $data = $this->mutationRepo->getAllDataBy($search, 0, 10000, $where);
        if ($type == 'excel') {
            return $this->downloadExcel($data, $params, $filename);
        } else {
            return $this->downloadPdf($request, $data, $params, $filename);
        }
    }

    private function downloadExcel($data, $params, $filename)
    {
        return Excel::download(new MutationStockReportExport($data, $params), $filename);
    }

    private function downloadPdf(Request $request, $data, $params, $filename)
    {
        $pdf = Pdf::loadView('accounting::stock.mutation_stock_report_pdf', [
            'data' => $data,
            'params' => $params,
        ])->setPaper('a4', 'landscape');

        if ($request->input('mode') === 'print') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    public function exportReportExcel(Request $request)
    {
        return $this->exportReportAsFormat($request, 'laporan-mutasi-stok.xlsx');
    }

    public function exportReportCsv(Request $request)
    {
        return $this->exportReportAsFormat($request, 'laporan-mutasi-stok.csv');
    }

    public function exportReportPdf(Request $request)
    {
        return $this->exportReportAsFormat($request, 'laporan-mutasi-stok.pdf', 'pdf');
    }

    public function export(Request $request)
    {
        return $this->exportAsExport($request, 'mutasi-stok.xlsx');
    }

    public function exportCsv(Request $request)
    {
        return $this->exportAsExport($request, 'mutasi-stok.csv');
    }

    private function exportAsExport(Request $request, string $filename)
    {
        $params = $this->setQueryParameters($request);
        $search = $params['search'];
        $page = $params['page'];
        $perpage = $params['perpage'];
        $where = $params['where'];

        $data = $this->mutationRepo->getAllDataBy($search, $page, $perpage, $where);
        return Excel::download(new MutationStockExport($data, $params), $filename);
    }

    public function exportPdf(Request $request)
    {
        $params = $this->setQueryParameters($request);
        $search = $params['search'];
        $page = $params['page'];
        $perpage = $params['perpage'];
        $where = $params['where'];

        $data = $this->mutationRepo->getAllDataBy($search, $page, $perpage, $where);
        $pdf = Pdf::loadView('accounting::stock.mutation_stock_report', [
            'data' => $data,
            'params' => $params,
        ]);
        return $pdf->download('mutasi-stok.pdf');
    }
}
